#!/bin/bash

# Quick Order Extension Installation Script for Magento 2.4.6+
# This script automates the installation and setup process

echo "======================================"
echo "Quick Order Extension Installation"
echo "======================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in Magento root directory
if [ ! -f "app/etc/env.php" ]; then
    print_error "This script must be run from the Magento root directory!"
    exit 1
fi

print_status "Starting Quick Order extension installation..."

# Step 1: Check PHP version
print_status "Checking PHP version..."
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -c 1-3)
if (( $(echo "$PHP_VERSION < 7.4" | bc -l) )); then
    print_error "PHP 7.4 or higher is required. Current version: $PHP_VERSION"
    exit 1
else
    print_status "PHP version check passed: $PHP_VERSION"
fi

# Step 2: Check if module directory exists
if [ ! -d "app/code/Lof/FastOrder" ]; then
    print_error "FastOrder module not found in app/code/Lof/FastOrder"
    print_error "Please copy the FastOrder directory to app/code/Lof/ first"
    exit 1
fi

# Step 3: Set proper permissions
print_status "Setting file permissions..."
chmod -R 755 app/code/Lof/FastOrder/
chmod +x app/code/Lof/FastOrder/install.sh

# Step 4: Enable the module
print_status "Enabling Lof_FastOrder module..."
php bin/magento module:enable Lof_FastOrder
if [ $? -ne 0 ]; then
    print_error "Failed to enable module"
    exit 1
fi

# Step 5: Run setup upgrade
print_status "Running setup upgrade..."
php bin/magento setup:upgrade
if [ $? -ne 0 ]; then
    print_error "Setup upgrade failed"
    exit 1
fi

# Step 6: Compile DI
print_status "Compiling dependency injection..."
php bin/magento setup:di:compile
if [ $? -ne 0 ]; then
    print_warning "DI compilation failed, but installation can continue"
fi

# Step 7: Deploy static content (production mode)
if [ "$1" = "production" ] || [ "$1" = "prod" ]; then
    print_status "Deploying static content for production..."
    php bin/magento setup:static-content:deploy
    if [ $? -ne 0 ]; then
        print_warning "Static content deployment failed"
    fi
fi

# Step 8: Clear cache
print_status "Clearing cache..."
php bin/magento cache:clean
php bin/magento cache:flush

# Step 9: Reindex if needed
if [ "$1" = "reindex" ] || [ "$2" = "reindex" ]; then
    print_status "Running reindex..."
    php bin/magento indexer:reindex
fi

# Step 10: Check module status
print_status "Checking module status..."
MODULE_STATUS=$(php bin/magento module:status Lof_FastOrder)
if [[ $MODULE_STATUS == *"Lof_FastOrder"* ]]; then
    print_status "✓ Lof_FastOrder module is enabled"
else
    print_error "✗ Module status check failed"
    exit 1
fi

# Step 11: Create sample CSV if it doesn't exist
if [ ! -f "pub/media/quick-order-template.csv" ]; then
    print_status "Creating sample CSV template..."
    mkdir -p pub/media
    cat > pub/media/quick-order-template.csv << 'EOF'
Product Name,Qty,Free Qty
Paracetamol 500mg Tablets,100,20
Amoxicillin 250mg Capsules,50,10
Omeprazole 20mg Tablets,25,4
Azithromycin 500mg Tablets,80,0
CROCIN ADVANCE TAB,100,0
EOF
fi

# Final status
echo ""
echo "======================================"
print_status "Installation completed successfully!"
echo "======================================"
echo ""
echo "Next steps:"
echo "1. Go to Stores > Configuration > Lof Extensions > Fast Order to configure"
echo "2. Visit your store and look for 'Quick Order' link in header/footer"
echo "3. Test the functionality with the sample CSV template"
echo ""
echo "URLs to test:"
echo "- Quick Order Page: /loffastforder/fastorder/quickorder"
echo "- CSV Template Download: /loffastforder/fastorder/downloadCsv"
echo ""
echo "For troubleshooting, check:"
echo "- var/log/system.log"
echo "- var/log/exception.log"
echo "- Browser console for JavaScript errors"
echo ""
print_warning "Note: If you're running in production mode, you may need to:"
print_warning "- Deploy static content: php bin/magento setup:static-content:deploy"
print_warning "- Enable modules in app/etc/config.php if needed"
echo ""
print_status "Happy bulk ordering! 🚀"