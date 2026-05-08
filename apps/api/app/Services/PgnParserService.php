<?php

namespace App\Services;

use App\Exceptions\PgnParseException;

class PgnParserService
{
    public function parse(string $pgn): array
    {
        $script = base_path('scripts/parse-pgn.mjs');

        // Write PGN to a temp file — avoids stdin pipe issues on Windows
        $tmpFile = tempnam(sys_get_temp_dir(), 'pgn_');
        if ($tmpFile === false) {
            throw new PgnParseException('Failed to create temporary file for PGN');
        }

        try {
            file_put_contents($tmpFile, $pgn);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open(['node', $script, $tmpFile], $descriptors, $pipes);

            if (! is_resource($process)) {
                throw new PgnParseException('Failed to spawn PGN parser process');
            }

            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
        } finally {
            @unlink($tmpFile);
        }

        if ($exitCode !== 0) {
            throw new PgnParseException('PGN parse failed: ' . trim($stderr));
        }

        $data = json_decode($stdout, true);

        if (! is_array($data)) {
            throw new PgnParseException('PGN parser returned invalid JSON');
        }

        return $data;
    }
}
