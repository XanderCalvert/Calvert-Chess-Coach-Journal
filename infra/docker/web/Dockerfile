FROM node:22-alpine

WORKDIR /app

EXPOSE 3000

CMD ["sh", "-c", "if [ -f package.json ]; then npm run dev -- --hostname 0.0.0.0; else echo 'Next.js app not scaffolded yet'; sleep infinity; fi"]
