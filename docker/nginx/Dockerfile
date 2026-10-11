FROM node:22-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM nginx:1.27-alpine

COPY public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
