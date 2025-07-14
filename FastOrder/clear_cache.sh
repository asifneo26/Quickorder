#!/bin/bash

# FastOrder Drag and Drop Implementation Cache Clear Script
echo "Clearing Magento cache after implementing drag and drop functionality..."

# Clear cache
php bin/magento cache:clean
php bin/magento cache:flush

# Deploy static content (if needed)
echo "Deploying static content..."
php bin/magento setup:static-content:deploy -f

# Clear generated files
rm -rf generated/code/*
rm -rf var/view_preprocessed/*
rm -rf pub/static/frontend/*

echo "Cache cleared successfully!"
echo "Please test the drag and drop functionality on the FastOrder page."