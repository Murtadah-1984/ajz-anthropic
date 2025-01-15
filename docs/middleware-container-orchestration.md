# Laravel Anthropic Middleware Container Orchestration

This document provides Docker and Kubernetes configurations for containerizing and orchestrating the middleware stack.

## Docker Configuration

### Development Dockerfile
```dockerfile
# laravel-anthropic/docker/dev/Dockerfile
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www
```

### Production Dockerfile
```dockerfile
# laravel-anthropic/docker/prod/Dockerfile
FROM php:8.2-fpm as builder

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy application files
COPY . .

# Final production image
FROM php:8.2-fpm

# Copy built application
COPY --from=builder /var/www /var/www

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Set permissions
RUN chown -R www-data:www-data /var/www
```

### Docker Compose Configuration
```yaml
# laravel-anthropic/docker-compose.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: docker/dev/Dockerfile
    volumes:
      - .:/var/www
    networks:
      - anthropic-network
    depends_on:
      - redis

  nginx:
    image: nginx:1.24-alpine
    ports:
      - "8080:80"
    volumes:
      - .:/var/www
      - ./docker/nginx/conf.d:/etc/nginx/conf.d
    networks:
      - anthropic-network
    depends_on:
      - app

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes:
      - redis-data:/data
    networks:
      - anthropic-network

networks:
  anthropic-network:
    driver: bridge

volumes:
  redis-data:
```

## Kubernetes Configuration

### Application Deployment
```yaml
# laravel-anthropic/k8s/app-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: anthropic-middleware
  namespace: anthropic
spec:
  replicas: 3
  selector:
    matchLabels:
      app: anthropic-middleware
  template:
    metadata:
      labels:
        app: anthropic-middleware
    spec:
      containers:
        - name: app
          image: your-registry/anthropic-middleware:latest
          resources:
            requests:
              cpu: "500m"
              memory: "512Mi"
            limits:
              cpu: "1000m"
              memory: "1Gi"
          env:
            - name: ANTHROPIC_API_KEY
              valueFrom:
                secretKeyRef:
                  name: anthropic-secrets
                  key: api-key
          readinessProbe:
            httpGet:
              path: /health
              port: 80
            initialDelaySeconds: 5
            periodSeconds: 10
          livenessProbe:
            httpGet:
              path: /health
              port: 80
            initialDelaySeconds: 15
            periodSeconds: 20
```

### Redis Configuration
```yaml
# laravel-anthropic/k8s/redis-statefulset.yaml
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: redis
  namespace: anthropic
spec:
  serviceName: redis
  replicas: 3
  selector:
    matchLabels:
      app: redis
  template:
    metadata:
      labels:
        app: redis
    spec:
      containers:
        - name: redis
          image: redis:7-alpine
          command:
            - redis-server
            - "/redis-config/redis.conf"
          ports:
            - containerPort: 6379
          volumeMounts:
            - name: redis-config
              mountPath: /redis-config
            - name: redis-data
              mountPath: /data
  volumeClaimTemplates:
    - metadata:
        name: redis-data
      spec:
        accessModes: ["ReadWriteOnce"]
        resources:
          requests:
            storage: 10Gi
```

### Helm Chart
```yaml
# laravel-anthropic/helm/Chart.yaml
apiVersion: v2
name: anthropic-middleware
description: Helm chart for Laravel Anthropic Middleware
version: 0.1.0
appVersion: "1.0.0"

# laravel-anthropic/helm/values.yaml
replicaCount: 3

image:
  repository: your-registry/anthropic-middleware
  tag: latest
  pullPolicy: IfNotPresent

service:
  type: ClusterIP
  port: 80

ingress:
  enabled: true
  className: nginx
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
  hosts:
    - host: api.example.com
      paths:
        - path: /
          pathType: Prefix

resources:
  requests:
    cpu: 500m
    memory: 512Mi
  limits:
    cpu: 1000m
    memory: 1Gi

redis:
  enabled: true
  architecture: replication
  auth:
    enabled: true
  master:
    persistence:
      size: 10Gi
  replica:
    replicaCount: 2
```

## Deployment Configurations

### Development
```yaml
# laravel-anthropic/k8s/overlays/dev/kustomization.yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization

resources:
  - ../../base

namespace: anthropic-dev

patches:
  - target:
      kind: Deployment
      name: anthropic-middleware
    patch: |-
      - op: replace
        path: /spec/replicas
        value: 1
      - op: replace
        path: /spec/template/spec/containers/0/resources/requests/cpu
        value: 250m
```

### Staging
```yaml
# laravel-anthropic/k8s/overlays/staging/kustomization.yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization

resources:
  - ../../base

namespace: anthropic-staging

patches:
  - target:
      kind: Deployment
      name: anthropic-middleware
    patch: |-
      - op: replace
        path: /spec/replicas
        value: 2
```

### Production
```yaml
# laravel-anthropic/k8s/overlays/prod/kustomization.yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization

resources:
  - ../../base

namespace: anthropic-prod

patches:
  - target:
      kind: Deployment
      name: anthropic-middleware
    patch: |-
      - op: replace
        path: /spec/replicas
        value: 3
```

## Monitoring Configuration

### Prometheus ServiceMonitor
```yaml
# laravel-anthropic/k8s/monitoring/servicemonitor.yaml
apiVersion: monitoring.coreos.com/v1
kind: ServiceMonitor
metadata:
  name: anthropic-middleware
  namespace: anthropic
spec:
  selector:
    matchLabels:
      app: anthropic-middleware
  endpoints:
    - port: http
      path: /metrics
      interval: 15s
```

### Grafana Dashboard
```yaml
# laravel-anthropic/k8s/monitoring/dashboard.yaml
apiVersion: integreatly.org/v1alpha1
kind: GrafanaDashboard
metadata:
  name: anthropic-middleware
  namespace: anthropic
spec:
  json: |
    {
      "dashboard": {
        "title": "Anthropic Middleware Metrics",
        "panels": [
          {
            "title": "Request Rate",
            "type": "graph",
            "datasource": "Prometheus",
            "targets": [
              {
                "expr": "rate(anthropic_requests_total[5m])"
              }
            ]
          },
          {
            "title": "Cache Hit Ratio",
            "type": "gauge",
            "datasource": "Prometheus",
            "targets": [
              {
                "expr": "anthropic_cache_hit_ratio"
              }
            ]
          }
        ]
      }
    }
```

## CI/CD Pipeline

### GitHub Actions Workflow
```yaml
# laravel-anthropic/.github/workflows/deploy.yml
name: Deploy Anthropic Middleware

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Build Docker image
        run: |
          docker build -t ${{ secrets.REGISTRY }}/anthropic-middleware:${{ github.sha }} \
            -f docker/prod/Dockerfile .
      
      - name: Push Docker image
        run: |
          docker push ${{ secrets.REGISTRY }}/anthropic-middleware:${{ github.sha }}

  deploy:
    needs: build
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Deploy to Kubernetes
        uses: azure/k8s-deploy@v1
        with:
          manifests: |
            k8s/overlays/prod/kustomization.yaml
          images: |
            ${{ secrets.REGISTRY }}/anthropic-middleware:${{ github.sha }}
```

These configurations provide:
- Docker development and production setups
- Kubernetes deployment manifests
- Helm chart for package management
- Environment-specific configurations
- Monitoring setup
- CI/CD pipeline

The configurations help with:
- Container orchestration
- Service scaling
- Resource management
- Configuration management
- Monitoring and metrics
- Automated deployment
