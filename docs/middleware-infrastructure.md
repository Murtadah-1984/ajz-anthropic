# Laravel Anthropic Middleware Infrastructure as Code

This document provides Terraform configurations for provisioning the required cloud infrastructure for the middleware stack.

## AWS Infrastructure

### Provider Configuration
```hcl
# laravel-anthropic/terraform/provider.tf
terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.0"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.0"
    }
  }
  
  backend "s3" {
    bucket = "anthropic-terraform-state"
    key    = "prod/terraform.tfstate"
    region = "us-west-2"
  }
}

provider "aws" {
  region = var.aws_region
}
```

### VPC Configuration
```hcl
# laravel-anthropic/terraform/vpc.tf
module "vpc" {
  source = "terraform-aws-modules/vpc/aws"

  name = "${var.environment}-anthropic-vpc"
  cidr = "10.0.0.0/16"

  azs             = ["${var.aws_region}a", "${var.aws_region}b", "${var.aws_region}c"]
  private_subnets = ["10.0.1.0/24", "10.0.2.0/24", "10.0.3.0/24"]
  public_subnets  = ["10.0.101.0/24", "10.0.102.0/24", "10.0.103.0/24"]

  enable_nat_gateway = true
  single_nat_gateway = var.environment != "prod"

  tags = {
    Environment = var.environment
    Project     = "anthropic-middleware"
  }
}
```

### EKS Cluster
```hcl
# laravel-anthropic/terraform/eks.tf
module "eks" {
  source = "terraform-aws-modules/eks/aws"

  cluster_name    = "${var.environment}-anthropic-cluster"
  cluster_version = "1.28"

  vpc_id     = module.vpc.vpc_id
  subnet_ids = module.vpc.private_subnets

  cluster_endpoint_private_access = true
  cluster_endpoint_public_access  = true

  eks_managed_node_groups = {
    general = {
      desired_size = 2
      min_size     = 1
      max_size     = 4

      instance_types = ["t3.large"]
      capacity_type  = "ON_DEMAND"
    }

    high_memory = {
      desired_size = 2
      min_size     = 1
      max_size     = 4

      instance_types = ["r6g.large"]
      capacity_type  = "ON_DEMAND"

      labels = {
        workload = "memory-intensive"
      }

      taints = [{
        key    = "workload"
        value  = "memory-intensive"
        effect = "NO_SCHEDULE"
      }]
    }
  }

  tags = {
    Environment = var.environment
    Project     = "anthropic-middleware"
  }
}
```

### ElastiCache Redis
```hcl
# laravel-anthropic/terraform/redis.tf
resource "aws_elasticache_cluster" "redis" {
  cluster_id           = "${var.environment}-anthropic-redis"
  engine              = "redis"
  node_type           = var.environment == "prod" ? "cache.r6g.large" : "cache.t3.medium"
  num_cache_nodes     = 1
  parameter_group_name = "default.redis7"
  port                = 6379
  security_group_ids  = [aws_security_group.redis.id]
  subnet_group_name   = aws_elasticache_subnet_group.redis.name

  tags = {
    Environment = var.environment
    Project     = "anthropic-middleware"
  }
}

resource "aws_elasticache_subnet_group" "redis" {
  name       = "${var.environment}-anthropic-redis-subnet"
  subnet_ids = module.vpc.private_subnets
}

resource "aws_security_group" "redis" {
  name_prefix = "${var.environment}-anthropic-redis-"
  vpc_id      = module.vpc.vpc_id

  ingress {
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [module.eks.cluster_security_group_id]
  }
}
```

### CloudWatch Monitoring
```hcl
# laravel-anthropic/terraform/monitoring.tf
resource "aws_cloudwatch_log_group" "anthropic" {
  name              = "/aws/anthropic/${var.environment}"
  retention_in_days = 30

  tags = {
    Environment = var.environment
    Project     = "anthropic-middleware"
  }
}

resource "aws_cloudwatch_metric_alarm" "api_errors" {
  alarm_name          = "${var.environment}-anthropic-api-errors"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "2"
  metric_name        = "ApiErrors"
  namespace          = "Anthropic"
  period             = "300"
  statistic          = "Sum"
  threshold          = "10"
  alarm_description  = "This metric monitors API errors"
  alarm_actions      = [aws_sns_topic.alerts.arn]

  dimensions = {
    Environment = var.environment
  }
}

resource "aws_sns_topic" "alerts" {
  name = "${var.environment}-anthropic-alerts"
}
```

### IAM Roles
```hcl
# laravel-anthropic/terraform/iam.tf
resource "aws_iam_role" "anthropic" {
  name = "${var.environment}-anthropic-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "eks.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_role_policy" "anthropic" {
  name = "${var.environment}-anthropic-policy"
  role = aws_iam_role.anthropic.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "elasticache:*",
          "cloudwatch:PutMetricData",
          "logs:CreateLogGroup",
          "logs:CreateLogStream",
          "logs:PutLogEvents"
        ]
        Resource = "*"
      }
    ]
  })
}
```

### S3 Bucket for Logs
```hcl
# laravel-anthropic/terraform/s3.tf
resource "aws_s3_bucket" "logs" {
  bucket = "${var.environment}-anthropic-logs"

  tags = {
    Environment = var.environment
    Project     = "anthropic-middleware"
  }
}

resource "aws_s3_bucket_lifecycle_configuration" "logs" {
  bucket = aws_s3_bucket.logs.id

  rule {
    id     = "log_retention"
    status = "Enabled"

    transition {
      days          = 30
      storage_class = "STANDARD_IA"
    }

    expiration {
      days = 90
    }
  }
}
```

### Variables
```hcl
# laravel-anthropic/terraform/variables.tf
variable "environment" {
  description = "Environment name"
  type        = string
}

variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "us-west-2"
}

variable "cluster_version" {
  description = "Kubernetes version"
  type        = string
  default     = "1.28"
}

variable "domain_name" {
  description = "Domain name for the application"
  type        = string
}
```

### Outputs
```hcl
# laravel-anthropic/terraform/outputs.tf
output "cluster_endpoint" {
  description = "EKS cluster endpoint"
  value       = module.eks.cluster_endpoint
}

output "redis_endpoint" {
  description = "Redis endpoint"
  value       = aws_elasticache_cluster.redis.cache_nodes[0].address
}

output "log_group_name" {
  description = "CloudWatch log group name"
  value       = aws_cloudwatch_log_group.anthropic.name
}
```

## Environment-Specific Configurations

### Production
```hcl
# laravel-anthropic/terraform/environments/prod/main.tf
module "anthropic_infrastructure" {
  source = "../../"

  environment = "prod"
  aws_region = "us-west-2"
  domain_name = "api.example.com"

  eks_managed_node_groups = {
    general = {
      desired_size = 3
      min_size     = 2
      max_size     = 6
      instance_types = ["t3.large"]
    }
    high_memory = {
      desired_size = 3
      min_size     = 2
      max_size     = 6
      instance_types = ["r6g.xlarge"]
    }
  }
}
```

### Staging
```hcl
# laravel-anthropic/terraform/environments/staging/main.tf
module "anthropic_infrastructure" {
  source = "../../"

  environment = "staging"
  aws_region = "us-west-2"
  domain_name = "staging-api.example.com"

  eks_managed_node_groups = {
    general = {
      desired_size = 2
      min_size     = 1
      max_size     = 4
      instance_types = ["t3.medium"]
    }
  }
}
```

These infrastructure configurations provide:
- VPC with public and private subnets
- EKS cluster with node groups
- ElastiCache Redis cluster
- CloudWatch monitoring
- IAM roles and policies
- S3 bucket for logs
- Environment-specific setups

The configurations help with:
- Infrastructure provisioning
- Resource management
- Security configuration
- Monitoring setup
- Environment isolation
- Cost optimization
