import os
import shutil
import subprocess
import time


def main() -> None:
    stockfish_binary = os.getenv("STOCKFISH_BINARY", "/usr/games/stockfish")
    redis_host = os.getenv("REDIS_HOST", "redis")
    api_internal_url = os.getenv("API_INTERNAL_URL", "http://api:8000")

    resolved_binary = shutil.which(stockfish_binary) or stockfish_binary

    print("Chess Coach engine worker starting")
    print(f"Redis host: {redis_host}")
    print(f"API URL: {api_internal_url}")
    print(f"Stockfish binary: {resolved_binary}")

    try:
        result = subprocess.run(
            [resolved_binary, "bench"],
            check=True,
            capture_output=True,
            text=True,
            timeout=30,
        )
    except Exception as exc:
        raise SystemExit(f"Stockfish check failed: {exc}") from exc

    print("Stockfish check passed")
    print(result.stdout.splitlines()[-1] if result.stdout else "No Stockfish output")

    while True:
        print("Engine worker idle; queue integration pending")
        time.sleep(60)


if __name__ == "__main__":
    main()
