<?php

namespace App\Services;

use RuntimeException;

class StockfishService
{
    private mixed $process;
    /** @var resource */
    private mixed $stdin;
    /** @var resource */
    private mixed $stdout;

    private int $depth;
    private int $timeout;

    public function __construct()
    {
        $binary  = config('services.stockfish.binary', '/usr/games/stockfish');
        $this->depth   = (int) config('services.stockfish.depth', 12);
        $this->timeout = (int) config('services.stockfish.timeout', 10);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->process = proc_open($binary, $descriptors, $pipes);

        if (! is_resource($this->process)) {
            throw new RuntimeException("Failed to open Stockfish process: {$binary}");
        }

        $this->stdin  = $pipes[0];
        $this->stdout = $pipes[1];

        stream_set_blocking($this->stdout, false);

        $this->send('uci');
        $this->readUntil('uciok');
        $this->send('isready');
        $this->readUntil('readyok');
    }

    /**
     * Analyse a FEN position and return evaluation info.
     *
     * @return array{best_move: string, cp: int, depth_reached: int, best_line: list<string>}
     */
    public function analyse(string $fen): array
    {
        $this->send("position fen {$fen}");
        $this->send("go depth {$this->depth}");

        $cp          = 0;
        $depthReached = 0;
        $bestLine    = [];
        $bestMove    = '';

        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            $read   = [$this->stdout];
            $write  = null;
            $except = null;

            $ready = stream_select($read, $write, $except, 0, 50_000);

            if ($ready === false) {
                break;
            }

            if ($ready === 0) {
                continue;
            }

            $line = fgets($this->stdout);
            if ($line === false) {
                continue;
            }

            $line = trim($line);

            if (str_starts_with($line, 'info depth')) {
                $depthReached = $this->parseDepth($line);
                $cp           = $this->parseCp($line, $cp);
                $bestLine     = $this->parsePv($line);
            }

            if (str_starts_with($line, 'bestmove')) {
                $parts    = explode(' ', $line);
                $bestMove = $parts[1] ?? '';
                break;
            }
        }

        if ($bestMove === '') {
            throw new RuntimeException("Stockfish did not return a best move within {$this->timeout}s for FEN: {$fen}");
        }

        return [
            'best_move'    => $bestMove,
            'cp'           => $cp,
            'depth_reached' => $depthReached,
            'best_line'    => $bestLine,
        ];
    }

    private function send(string $command): void
    {
        fwrite($this->stdin, $command . "\n");
    }

    private function readUntil(string $token): void
    {
        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            $read   = [$this->stdout];
            $write  = null;
            $except = null;

            $ready = stream_select($read, $write, $except, 0, 50_000);

            if ($ready > 0) {
                $line = fgets($this->stdout);
                if ($line !== false && str_contains(trim($line), $token)) {
                    return;
                }
            }
        }

        throw new RuntimeException("Stockfish did not respond with '{$token}' within {$this->timeout}s");
    }

    private function parseDepth(string $line): int
    {
        if (preg_match('/\bdepth\s+(\d+)/', $line, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    private function parseCp(string $line, int $current): int
    {
        // Handle centipawn score
        if (preg_match('/score cp (-?\d+)/', $line, $m)) {
            return (int) $m[1];
        }

        // Map mate score to ±10000 cp
        if (preg_match('/score mate (-?\d+)/', $line, $m)) {
            return (int) $m[1] > 0 ? 10000 : -10000;
        }

        return $current;
    }

    /** @return list<string> */
    private function parsePv(string $line): array
    {
        if (preg_match('/ pv (.+)$/', $line, $m)) {
            return array_slice(explode(' ', trim($m[1])), 0, 4);
        }
        return [];
    }

    public function __destruct()
    {
        if (is_resource($this->stdin)) {
            fwrite($this->stdin, "quit\n");
            fclose($this->stdin);
        }
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }
        if (is_resource($this->process)) {
            proc_close($this->process);
        }
    }
}
