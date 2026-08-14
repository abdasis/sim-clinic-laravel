# Nginx Dockerfile — reverse proxy web + api for sim-clinic
FROM nginx:alpine
COPY docker/nginx.conf /etc/nginx/nginx.conf
EXPOSE 80
