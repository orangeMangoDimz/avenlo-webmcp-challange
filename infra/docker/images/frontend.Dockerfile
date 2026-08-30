FROM node:20-alpine AS build

ARG APP_DIR
ARG VITE_ADMIN_LOGO_STYLE=image
ARG VITE_API_BASE_URL
ARG VITE_CLIENT_API_BASE_URL
ARG VITE_CLIENT_BASE_URL
ARG VITE_CLIENT_LOGO_STYLE=image
ARG VITE_LOGO_TEXT=CRM

ENV VITE_ADMIN_LOGO_STYLE=${VITE_ADMIN_LOGO_STYLE} \
    VITE_API_BASE_URL=${VITE_API_BASE_URL} \
    VITE_CLIENT_API_BASE_URL=${VITE_CLIENT_API_BASE_URL} \
    VITE_CLIENT_BASE_URL=${VITE_CLIENT_BASE_URL} \
    VITE_CLIENT_LOGO_STYLE=${VITE_CLIENT_LOGO_STYLE} \
    VITE_LOGO_TEXT=${VITE_LOGO_TEXT}

WORKDIR /app

COPY ${APP_DIR}/package.json ${APP_DIR}/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY ${APP_DIR}/ ./
RUN npm run build

FROM nginx:1.27-alpine

COPY infra/docker/images/frontend.nginx.conf /etc/nginx/nginx.conf
COPY --from=build /app/dist /usr/share/nginx/html

RUN chown -R nginx:nginx /usr/share/nginx/html

USER nginx

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/ >/dev/null || exit 1

CMD ["nginx", "-g", "daemon off;"]
