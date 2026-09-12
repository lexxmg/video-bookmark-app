# tailwind-compiler/Dockerfile
FROM node:20-alpine

WORKDIR /src

# Устанавливаем CLI и сам пакет tailwindcss глобально
RUN npm install -g @tailwindcss/cli tailwindcss

# Указываем Node.js, где искать глобальные пакеты
ENV NODE_PATH=/usr/local/lib/node_modules

CMD ["tailwindcss", "--help"]
