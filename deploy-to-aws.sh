#!/bin/bash

# AWS Deployment Script for NDR Test Server
# This script deploys the Docker container to AWS ECS with Application Load Balancer

set -e

# Configuration variables - EDIT THESE
AWS_REGION="us-east-1"
AWS_ACCOUNT_ID="236104224262"
CLUSTER_NAME="ndr-test-cluster"
SERVICE_NAME="ndr-test-service"
TASK_FAMILY="ndr-test-server"
ECR_REPO="ndr-test-server"
IMAGE_TAG="latest"

echo "=========================================="
echo "NDR Test Server - AWS Deployment"
echo "=========================================="
echo ""

# Check if AWS CLI is installed
if ! command -v aws &> /dev/null; then
    echo "❌ AWS CLI not found. Please install it first:"
    echo "   brew install awscli"
    exit 1
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo "❌ Docker is not running. Please start Docker Desktop."
    exit 1
fi

echo "✅ Prerequisites check passed"
echo ""

# Step 1: Create ECR repository if it doesn't exist
echo "📦 Step 1: Creating ECR repository..."
aws ecr describe-repositories --repository-names $ECR_REPO --region $AWS_REGION 2>/dev/null || \
aws ecr create-repository --repository-name $ECR_REPO --region $AWS_REGION

# Step 2: Authenticate Docker to ECR
echo "🔐 Step 2: Authenticating Docker to ECR..."
aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com

# Step 3: Build and tag the Docker image
echo "🏗️  Step 3: Building Docker image..."
docker build -t $ECR_REPO:$IMAGE_TAG .

# Step 4: Tag for ECR
echo "🏷️  Step 4: Tagging image for ECR..."
docker tag $ECR_REPO:$IMAGE_TAG $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPO:$IMAGE_TAG

# Step 5: Push to ECR
echo "⬆️  Step 5: Pushing image to ECR..."
docker push $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPO:$IMAGE_TAG

# Step 6: Create CloudWatch log group
echo "📊 Step 6: Creating CloudWatch log group..."
aws logs create-log-group --log-group-name /ecs/$TASK_FAMILY --region $AWS_REGION 2>/dev/null || echo "Log group already exists"

# Step 6.5: Create ECS Task Execution Role if it doesn't exist
echo "🔑 Step 6.5: Ensuring ECS Task Execution Role exists..."
if ! aws iam get-role --role-name ecsTaskExecutionRole 2>/dev/null; then
    echo "Creating ecsTaskExecutionRole..."
    
    # Create trust policy
    cat > /tmp/trust-policy.json <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "ecs-tasks.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
EOF
    
    # Create role
    aws iam create-role \
      --role-name ecsTaskExecutionRole \
      --assume-role-policy-document file:///tmp/trust-policy.json \
      --region $AWS_REGION
    
    # Attach AWS managed policy
    aws iam attach-role-policy \
      --role-name ecsTaskExecutionRole \
      --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy \
      --region $AWS_REGION
    
    rm /tmp/trust-policy.json
    echo "✅ Created ecsTaskExecutionRole"
else
    echo "✅ ecsTaskExecutionRole already exists"
fi

# Step 7: Register task definition
echo "📝 Step 7: Registering ECS task definition..."
sed "s|<AWS_ACCOUNT_ID>|$AWS_ACCOUNT_ID|g" aws-ecs-task-definition.json | \
  sed "s|<REGION>|$AWS_REGION|g" > /tmp/task-definition.json
aws ecs register-task-definition --cli-input-json file:///tmp/task-definition.json --region $AWS_REGION
rm /tmp/task-definition.json

# Step 8: Create ECS cluster if it doesn't exist
echo "🖥️  Step 8: Creating ECS cluster..."
aws ecs describe-clusters --clusters $CLUSTER_NAME --region $AWS_REGION 2>/dev/null | grep -q "ACTIVE" || \
aws ecs create-cluster --cluster-name $CLUSTER_NAME --region $AWS_REGION

# Step 9: Get default VPC and subnets
echo "🌐 Step 9: Setting up networking..."
VPC_ID=$(aws ec2 describe-vpcs --filters "Name=isDefault,Values=true" --query "Vpcs[0].VpcId" --output text --region $AWS_REGION)
echo "Using VPC: $VPC_ID"

SUBNETS=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" --query "Subnets[0:2].SubnetId" --output text --region $AWS_REGION)
SUBNET_1=$(echo $SUBNETS | awk '{print $1}')
SUBNET_2=$(echo $SUBNETS | awk '{print $2}')
echo "Using subnets: $SUBNET_1, $SUBNET_2"

# Step 10: Create security group
echo "🔒 Step 10: Creating security group..."
SG_NAME="ndr-test-sg"
SG_ID=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=$SG_NAME" "Name=vpc-id,Values=$VPC_ID" --query "SecurityGroups[0].GroupId" --output text --region $AWS_REGION 2>/dev/null)

if [ "$SG_ID" == "None" ] || [ -z "$SG_ID" ]; then
    SG_ID=$(aws ec2 create-security-group \
      --group-name $SG_NAME \
      --description "Security group for NDR test server" \
      --vpc-id $VPC_ID \
      --region $AWS_REGION \
      --output text --query 'GroupId')
    
    # Allow HTTP traffic
    aws ec2 authorize-security-group-ingress \
      --group-id $SG_ID \
      --protocol tcp \
      --port 80 \
      --cidr 0.0.0.0/0 \
      --region $AWS_REGION
    
    echo "Created security group: $SG_ID"
else
    echo "Using existing security group: $SG_ID"
fi

# Step 11: Create target group
echo "🎯 Step 11: Creating target group..."
TG_NAME="ndr-test-tg"
TARGET_GROUP_ARN=$(aws elbv2 describe-target-groups --names $TG_NAME --region $AWS_REGION --query "TargetGroups[0].TargetGroupArn" --output text 2>/dev/null || echo "")

if [ "$TARGET_GROUP_ARN" == "None" ] || [ -z "$TARGET_GROUP_ARN" ] || [ "$TARGET_GROUP_ARN" == "null" ]; then
    TARGET_GROUP_ARN=$(aws elbv2 create-target-group \
      --name $TG_NAME \
      --protocol HTTP \
      --port 80 \
      --vpc-id $VPC_ID \
      --target-type ip \
      --health-check-path /submit.php \
      --health-check-interval-seconds 30 \
      --health-check-timeout-seconds 5 \
      --healthy-threshold-count 2 \
      --unhealthy-threshold-count 3 \
      --region $AWS_REGION \
      --query 'TargetGroups[0].TargetGroupArn' \
      --output text)
    echo "Created target group: $TARGET_GROUP_ARN"
else
    echo "Using existing target group: $TARGET_GROUP_ARN"
fi

# Step 12: Create Application Load Balancer
echo "⚖️  Step 12: Creating Application Load Balancer..."
ALB_NAME="ndr-test-alb"
ALB_ARN=$(aws elbv2 describe-load-balancers --names $ALB_NAME --region $AWS_REGION --query "LoadBalancers[0].LoadBalancerArn" --output text 2>/dev/null || echo "")

if [ "$ALB_ARN" == "None" ] || [ -z "$ALB_ARN" ] || [ "$ALB_ARN" == "null" ]; then
    ALB_ARN=$(aws elbv2 create-load-balancer \
      --name $ALB_NAME \
      --subnets $SUBNET_1 $SUBNET_2 \
      --security-groups $SG_ID \
      --scheme internet-facing \
      --type application \
      --ip-address-type ipv4 \
      --region $AWS_REGION \
      --query 'LoadBalancers[0].LoadBalancerArn' \
      --output text)
    echo "Created load balancer: $ALB_ARN"
    
    # Wait for ALB to be active
    echo "Waiting for load balancer to become active..."
    aws elbv2 wait load-balancer-available --load-balancer-arns $ALB_ARN --region $AWS_REGION
else
    echo "Using existing load balancer: $ALB_ARN"
fi

# Step 13: Create listener
echo "👂 Step 13: Creating listener..."
LISTENER_ARN=$(aws elbv2 describe-listeners --load-balancer-arn $ALB_ARN --region $AWS_REGION --query "Listeners[?Port==\`80\`].ListenerArn" --output text 2>/dev/null || echo "")

if [ -z "$LISTENER_ARN" ] || [ "$LISTENER_ARN" == "null" ]; then
    aws elbv2 create-listener \
      --load-balancer-arn $ALB_ARN \
      --protocol HTTP \
      --port 80 \
      --default-actions Type=forward,TargetGroupArn=$TARGET_GROUP_ARN \
      --region $AWS_REGION
    echo "Created listener"
else
    echo "Listener already exists"
fi

# Step 14: Create or update ECS service
echo "🚀 Step 14: Creating ECS service..."
SERVICE_EXISTS=$(aws ecs describe-services --cluster $CLUSTER_NAME --services $SERVICE_NAME --region $AWS_REGION --query "services[0].status" --output text 2>/dev/null || echo "")

if [ "$SERVICE_EXISTS" == "ACTIVE" ]; then
    echo "Service already exists, updating..."
    aws ecs update-service \
      --cluster $CLUSTER_NAME \
      --service $SERVICE_NAME \
      --force-new-deployment \
      --region $AWS_REGION > /dev/null
    echo "Service updated with new deployment"
elif [ "$SERVICE_EXISTS" == "INACTIVE" ]; then
    echo "Service is inactive, deleting and recreating..."
    aws ecs delete-service --cluster $CLUSTER_NAME --service $SERVICE_NAME --force --region $AWS_REGION > /dev/null
    sleep 5
    SERVICE_EXISTS=""
fi

if [ -z "$SERVICE_EXISTS" ] || [ "$SERVICE_EXISTS" == "None" ]; then
    aws ecs create-service \
      --cluster $CLUSTER_NAME \
      --service-name $SERVICE_NAME \
      --task-definition $TASK_FAMILY \
      --desired-count 1 \
      --launch-type FARGATE \
      --network-configuration "awsvpcConfiguration={subnets=[$SUBNET_1,$SUBNET_2],securityGroups=[$SG_ID],assignPublicIp=ENABLED}" \
      --load-balancers "targetGroupArn=$TARGET_GROUP_ARN,containerName=ndr-test-server,containerPort=80" \
      --health-check-grace-period-seconds 60 \
      --region $AWS_REGION > /dev/null
    echo "Service created"
fi

# Get ALB DNS name
ALB_DNS=$(aws elbv2 describe-load-balancers \
  --load-balancer-arns $ALB_ARN \
  --query 'LoadBalancers[0].DNSName' \
  --output text \
  --region $AWS_REGION)

echo ""
echo "=========================================="
echo "✅ Deployment Complete!"
echo "=========================================="
echo ""
echo "🌐 Your NDR Test Server is available at:"
echo ""
echo "   http://$ALB_DNS/submit.php"
echo ""
echo "⏳ Note: It may take 2-3 minutes for the service to start"
echo "   and health checks to pass before the URL is accessible."
echo ""
echo "📊 Monitor deployment status:"
echo "   aws ecs describe-services --cluster $CLUSTER_NAME --services $SERVICE_NAME --region $AWS_REGION"
echo ""
echo "📝 View logs:"
echo "   aws logs tail /ecs/$TASK_FAMILY --follow --region $AWS_REGION"
echo ""
echo "🧪 Test with Python client:"
echo "   python3 cobalt_client.py --domain $ALB_DNS"
echo ""
echo "🧪 Test with PowerShell client:"
echo "   .\cobalt_client.ps1 -Domain $ALB_DNS"
echo ""
