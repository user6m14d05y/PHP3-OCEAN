# ==========================================
# Giai đoạn 1: Build VueJS App (Node.js)
# ==========================================
FROM node:20-alpine as build-stage

WORKDIR /app

# Copy package.json và cài đặt dependencies trước (tận dụng Docker cache)
COPY package*.json ./
RUN npm ci

# Copy toàn bộ code frontend và build
COPY . .
RUN npm run build

# ==========================================
# Giai đoạn 2: Serve tĩnh bằng Nginx
# ==========================================
FROM nginx:alpine as production-stage

# Xóa trang default của Nginx
RUN rm -rf /usr/share/nginx/html/*

# Copy kết quả build từ stage 1 sang thư mục html của Nginx
COPY --from=build-stage /app/dist /usr/share/nginx/html

# Expose port 80 cho Nginx container
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
