# AWS Deployment Guide

This guide walks you through deploying the NDR Test Server to AWS using ECS Fargate and Application Load Balancer.

## Prerequisites

1. **AWS Account** with appropriate permissions
2. **AWS CLI** installed and configured
   ```bash
   brew install awscli
   aws configure
   ```
3. **Docker Desktop** running
4. **AWS Account ID** - Find it in AWS Console > Account dropdown

## Quick Deployment

### Step 1: Configure Deployment Script

Edit `deploy-to-aws.sh` and update these variables:

```bash
AWS_REGION="us-east-1"              # Your preferred AWS region
AWS_ACCOUNT_ID="123456789012"       # Your AWS account ID
CLUSTER_NAME="ndr-test-cluster"     # ECS cluster name
SERVICE_NAME="ndr-test-service"     # ECS service name
```

### Step 2: Run Deployment Script

```bash
chmod +x deploy-to-aws.sh
./deploy-to-aws.sh
```

This script will:
- ✅ Create ECR repository
- ✅ Build and push Docker image
- ✅ Create CloudWatch log group
- ✅ Register ECS task definition
- ✅ Create ECS cluster

### Step 3: Create VPC Infrastructure (First Time Only)

If you don't have a VPC with subnets and security group:

```bash
# Get default VPC
VPC_ID=$(aws ec2 describe-vpcs --filters "Name=isDefault,Values=true" --query "Vpcs[0].VpcId" --output text --region us-east-1)

# Get subnets in default VPC
SUBNET_1=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" --query "Subnets[0].SubnetId" --output text --region us-east-1)
SUBNET_2=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" --query "Subnets[1].SubnetId" --output text --region us-east-1)

# Create security group
SG_ID=$(aws ec2 create-security-group \
  --group-name ndr-test-sg \
  --description "Security group for NDR test server" \
  --vpc-id $VPC_ID \
  --region us-east-1 \
  --output text --query 'GroupId')

# Allow HTTP traffic
aws ec2 authorize-security-group-ingress \
  --group-id $SG_ID \
  --protocol tcp \
  --port 80 \
  --cidr 0.0.0.0/0 \
  --region us-east-1

echo "VPC_ID: $VPC_ID"
echo "SUBNET_1: $SUBNET_1"
echo "SUBNET_2: $SUBNET_2"
echo "SG_ID: $SG_ID"
```

### Step 4: Create Application Load Balancer

```bash
# Create target group
TARGET_GROUP_ARN=$(aws elbv2 create-target-group \
  --name ndr-test-tg \
  --protocol HTTP \
  --port 80 \
  --vpc-id $VPC_ID \
  --target-type ip \
  --health-check-path /submit.php \
  --region us-east-1 \
  --query 'TargetGroups[0].TargetGroupArn' \
  --output text)

# Create Application Load Balancer
ALB_ARN=$(aws elbv2 create-load-balancer \
  --name ndr-test-alb \
  --subnets $SUBNET_1 $SUBNET_2 \
  --security-groups $SG_ID \
  --region us-east-1 \
  --query 'LoadBalancers[0].LoadBalancerArn' \
  --output text)

# Create listener
aws elbv2 create-listener \
  --load-balancer-arn $ALB_ARN \
  --protocol HTTP \
  --port 80 \
  --default-actions Type=forward,TargetGroupArn=$TARGET_GROUP_ARN \
  --region us-east-1

# Get ALB DNS name
ALB_DNS=$(aws elbv2 describe-load-balancers \
  --load-balancer-arns $ALB_ARN \
  --query 'LoadBalancers[0].DNSName' \
  --output text \
  --region us-east-1)

echo "ALB DNS: $ALB_DNS"
echo "Target Group ARN: $TARGET_GROUP_ARN"
```

### Step 5: Create ECS Service

```bash
aws ecs create-service \
  --cluster ndr-test-cluster \
  --service-name ndr-test-service \
  --task-definition ndr-test-server \
  --desired-count 1 \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$SUBNET_1,$SUBNET_2],securityGroups=[$SG_ID],assignPublicIp=ENABLED}" \
  --load-balancers "targetGroupArn=$TARGET_GROUP_ARN,containerName=ndr-test-server,containerPort=80" \
  --region us-east-1
```

### Step 6: Access Your Server

Your server will be available at:
```
http://$ALB_DNS/submit.php
```

Wait 2-3 minutes for the service to start and health checks to pass.

## Testing the Deployment

Once deployed, test with the Python client:

```bash
python3 cobalt_client.py --domain $ALB_DNS
```

Or with PowerShell:

```powershell
.\cobalt_client.ps1 -Domain $ALB_DNS
```

## Monitoring

View logs in CloudWatch:
```bash
aws logs tail /ecs/ndr-test-server --follow --region us-east-1
```

Check service status:
```bash
aws ecs describe-services \
  --cluster ndr-test-cluster \
  --services ndr-test-service \
  --region us-east-1
```

## Cost Estimate

- **ECS Fargate**: ~$10-15/month (0.25 vCPU, 0.5 GB)
- **Application Load Balancer**: ~$16/month + data transfer
- **ECR Storage**: ~$0.10/GB/month
- **CloudWatch Logs**: ~$0.50/GB ingested

**Total**: ~$25-30/month for 24/7 operation

## Updating the Service

After making changes to `submit.php`:

```bash
./deploy-to-aws.sh

# Force new deployment
aws ecs update-service \
  --cluster ndr-test-cluster \
  --service ndr-test-service \
  --force-new-deployment \
  --region us-east-1
```

## Cleanup

To remove all AWS resources:

```bash
# Delete service
aws ecs update-service \
  --cluster ndr-test-cluster \
  --service ndr-test-service \
  --desired-count 0 \
  --region us-east-1

aws ecs delete-service \
  --cluster ndr-test-cluster \
  --service ndr-test-service \
  --region us-east-1

# Delete cluster
aws ecs delete-cluster \
  --cluster ndr-test-cluster \
  --region us-east-1

# Delete load balancer
aws elbv2 delete-load-balancer --load-balancer-arn $ALB_ARN --region us-east-1

# Delete target group (wait 30 seconds after ALB deletion)
sleep 30
aws elbv2 delete-target-group --target-group-arn $TARGET_GROUP_ARN --region us-east-1

# Delete security group
aws ec2 delete-security-group --group-id $SG_ID --region us-east-1

# Delete ECR repository
aws ecr delete-repository \
  --repository-name ndr-test-server \
  --force \
  --region us-east-1

# Delete CloudWatch log group
aws logs delete-log-group \
  --log-group-name /ecs/ndr-test-server \
  --region us-east-1
```

## Troubleshooting

### Service won't start
- Check ECS console for task errors
- Verify security group allows port 80
- Check CloudWatch logs for errors

### Can't access ALB
- Wait 2-3 minutes for health checks
- Verify security group rules
- Check target group health status

### Docker push fails
- Ensure AWS CLI is configured: `aws configure`
- Check ECR permissions in IAM
- Verify region matches in all commands
