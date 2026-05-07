FROM python:3.12-slim-bookworm

WORKDIR /worker

RUN apt-get update \
    && apt-get install -y --no-install-recommends stockfish \
    && rm -rf /var/lib/apt/lists/*

CMD ["python", "worker.py"]
