# syntax=docker/dockerfile:1

# Build stage
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
COPY tsconfig*.json ./
COPY vite.config.ts ./
COPY eslint.config.js ./
COPY postcss.config.js ./
COPY tailwind.config.js ./
# Build-time API base URL for Vite (default to '/api' to use nginx proxy)
ARG VITE_API_BASE_URL=https://api.aspriai.my.id
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}
RUN npm ci || npm install
COPY . .
RUN npm run build

# Nginx stage
FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
