#!/bin/bash

# Quick Update Script for NDR Test Server
# Rebuilds and deploys changes to AWS ECS

set -e

# Configuration
AWS_REGION="us-east-1"
AWS_ACCOUNT_ID="236104224262"
ECR_REPO="ndr-test-server"
IMAGE_TAG="latest"
CLUSTER_NAME="ndr-test-cluster"
SERVICE_NAME="ndr-test-service"

echo "=========================================="
echo "NDR Test Server - Quick Update"
echo "=========================================="
echo ""

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo "❌ Docker is not running. Please start Docker Desktop."
    exit 1
fi

# Step 1: Build Docker image for AMD64
echo "🏗️  Step 1: Building Docker image for AMD64..."
docker build --platform linux/amd64 -t $ECR_REPO:$IMAGE_TAG .

# Step 2: Tag for ECR
echo "🏷️  Step 2: Tagging image for ECR..."
docker tag $ECR_REPO:$IMAGE_TAG $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPO:$IMAGE_TAG

# Step 3: Authenticate to ECR (only if needed)
echo "🔐 Step 3: Authenticating to ECR..."
aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com 2>/dev/null || true

# Step 4: Push to ECR
echo "⬆️  Step 4: Pushing image to ECR..."
docker push $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPO:$IMAGE_TAG

# Step 5: Force new deployment
echo "🚀 Step 5: Deploying to ECS..."
aws ecs update-service \
  --cluster $CLUSTER_NAME \
  --service $SERVICE_NAME \
  --force-new-deployment \
  --region $AWS_REGION \
  --query 'service.serviceName' \
  --output text > /dev/null

# Get ALB DNS
ALB_DNS=$(aws elbv2 describe-load-balancers \
  --names ndr-test-alb \
  --region $AWS_REGION \
  --query 'LoadBalancers[0].DNSName' \
  --output text)

echo ""
echo "=========================================="
echo "✅ Update Complete!"
echo "=========================================="
echo ""
echo "🌐 Your updated server will be live in ~2 minutes at:"
echo "   http://$ALB_DNS/submit.php"
echo ""
echo "📊 Monitor deployment status:"
echo "   aws ecs describe-services --cluster $CLUSTER_NAME --services $SERVICE_NAME --region $AWS_REGION --query 'services[0].deployments' --output table"
echo ""
echo "📝 View logs:"
echo "   aws logs tail /ecs/ndr-test-server --follow --region $AWS_REGION"
echo ""
