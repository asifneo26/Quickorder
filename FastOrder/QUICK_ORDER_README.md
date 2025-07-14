# Quick Order Extension for Magento 2.4.6+

## Overview

The Quick Order extension provides a comprehensive bulk ordering system for B2B pharmaceutical commerce platforms. It enables distributors to efficiently upload and manage large orders through CSV files while leveraging intelligent fuzzy matching capabilities.

## Features

### Core Functionality
- **CSV Upload**: Upload CSV files with up to 2000 products (max 5MB file size)
- **Intelligent Fuzzy Matching**: Advanced product name matching with pharmaceutical-specific algorithms
- **Manual Product Search**: Real-time search for products by name, SKU, or manufacturer
- **Confidence Indicators**: Visual indicators showing match confidence (High/Medium/Low)
- **Stock Validation**: Real-time stock checking and quantity validation
- **Bulk Add to Cart**: Add all matched products to cart in one action

### User Interface
- **Modern Vue.js Frontend**: Responsive, interactive interface
- **Drag & Drop Upload**: Intuitive file upload with drag-and-drop support
- **Product Grid**: Comprehensive order review with inline editing
- **Error Handling**: Clear error messages and validation feedback
- **Mobile Responsive**: Optimized for desktop and mobile devices

### Technical Features
- **Fuzzy Matching Algorithm**: Multiple matching strategies including:
  - Levenshtein distance
  - Jaro-Winkler similarity
  - Token-based matching
  - Pharmaceutical-specific matching
- **Performance Optimized**: Chunked processing for large files
- **REST API**: RESTful endpoints for all operations
- **Magento 2.4.6+ Compatible**: Full compatibility with latest Magento

## Installation

1. **Copy Extension Files**
   ```bash
   cp -r FastOrder app/code/Lof/FastOrder/
   ```

2. **Enable Extension**
   ```bash
   php bin/magento module:enable Lof_FastOrder
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento cache:flush
   ```

3. **Configure Permissions**
   ```bash
   chmod -R 755 app/code/Lof/FastOrder/
   ```

## Usage

### Accessing Quick Order
- Navigate to the "Quick Order" link in the header, footer, or mini-cart area
- URL: `yourstore.com/loffastforder/fastorder/quickorder`

### CSV Upload Process

1. **Download Template**
   - Click "Download CSV Template" to get the proper format
   - Template includes: Product Name, Qty, Free Qty

2. **Prepare Your CSV**
   - Required columns: Product Name, Qty
   - Optional columns: Free Qty
   - Maximum 2000 products per file
   - Maximum file size: 5MB

3. **Upload CSV**
   - Drag and drop your CSV file or click "Choose File"
   - The system will automatically process and match products

4. **Review Matches**
   - Green indicator: High confidence match (≥80%)
   - Yellow indicator: Medium confidence match (60-79%)
   - Red indicator: Low confidence match (<60%)

5. **Correct Matches**
   - Use dropdowns to select correct products for low-confidence matches
   - Adjust quantities as needed
   - Remove unwanted items

6. **Add to Cart**
   - Click "Add to Cart" when satisfied with your order
   - All validated items will be added to your shopping cart

### Manual Product Search

1. **Search Products**
   - Type product name, SKU, or manufacturer in the search box
   - Results appear in real-time as you type

2. **Add Products**
   - Click on search results to add to your order
   - Adjust quantities in the order grid

## CSV Format

### Required Format
```csv
Product Name,Qty,Free Qty
Paracetamol 500mg Tablets,100,20
Amoxicillin 250mg Capsules,50,10
Omeprazole 20mg Tablets,25,4
```

### Supported Column Names
- **Product Name**: "Product Name", "Name", "Item Name", "Drug Name"
- **Quantity**: "Qty", "Quantity", "Order Qty", "Order Quantity"
- **Free Quantity**: "Free Qty", "Free Quantity", "Bonus Qty"

## Fuzzy Matching Algorithm

The system uses multiple matching strategies to find the best product matches:

### Matching Techniques
1. **Exact Match**: Direct name comparison
2. **Levenshtein Distance**: Character-based similarity
3. **Jaro-Winkler**: String similarity optimized for names
4. **Token Matching**: Word-by-word comparison
5. **Substring Matching**: Partial string matches
6. **Pharmaceutical Matching**: Drug-specific algorithms

### Pharmaceutical Enhancements
- **Abbreviation Expansion**: "tab" → "tablet", "cap" → "capsule"
- **Dosage Matching**: Recognizes "500mg", "20ml", etc.
- **Form Recognition**: Identifies tablets, capsules, injections
- **Manufacturer Consideration**: Includes brand matching

### Confidence Levels
- **High (≥80%)**: Very likely correct, auto-selected
- **Medium (60-79%)**: Probably correct, review recommended
- **Low (<60%)**: Likely incorrect, manual selection needed

## API Endpoints

### Search Products
```
POST /loffastforder/fastorder/searchProducts
Parameters: query, store_id, limit
```

### Upload CSV
```
POST /loffastforder/fastorder/uploadCsv
Parameters: csv_file, store_id
```

### Add to Cart
```
POST /loffastforder/fastorder/addToCart
Parameters: items[], store_id
```

### Download Template
```
GET /loffastforder/fastorder/downloadCsv
```

## Configuration

### System Configuration
Navigate to: `Stores > Configuration > Lof Extensions > Fast Order`

### Available Settings
- Enable/Disable extension
- Configure search attributes
- Set upload limits
- Customize matching thresholds

## Troubleshooting

### Common Issues

1. **CSV Upload Fails**
   - Check file format (must be .csv)
   - Verify file size (max 5MB)
   - Ensure proper column headers

2. **No Product Matches**
   - Check product names in CSV
   - Verify products exist in catalog
   - Try using SKU instead of product name

3. **Low Match Confidence**
   - Review product names for typos
   - Use manufacturer-specific names
   - Include dosage information

### Error Messages

- **"Invalid file format"**: Upload only CSV files
- **"File too large"**: Reduce file size or split into multiple files
- **"No products found"**: Check search terms or product availability
- **"Quantity errors"**: Verify stock availability

## Performance Considerations

### Optimization Tips
- **File Size**: Keep CSV files under 2MB for best performance
- **Product Count**: Process in batches of 500-1000 products
- **Server Resources**: Ensure adequate memory for large uploads
- **Caching**: Enable Magento caching for better performance

### System Requirements
- **Magento**: 2.4.6 or higher
- **PHP**: 7.4 or higher
- **Memory**: Minimum 512MB for large CSV processing
- **Browser**: Modern browsers with JavaScript enabled

## Support

### Documentation
- Review this README for detailed instructions
- Check Magento logs for error details
- Test with sample CSV template first

### Best Practices
- Always download and use the provided template
- Test with small CSV files first
- Review all matches before adding to cart
- Keep product names consistent with catalog

## Version History

### Version 1.0.0
- Initial release with basic CSV upload
- Simple product matching

### Version 2.0.0 (Current)
- Advanced fuzzy matching algorithm
- Vue.js frontend interface
- Enhanced error handling
- Mobile responsive design
- Improved performance
- Pharmaceutical-specific matching
- Real-time search functionality

## License

This extension is licensed under the MIT License. See LICENSE.txt for details.

## Support & Contributions

For support, bug reports, or feature requests, please contact the development team or create an issue in the project repository.