define([
    'jquery',
    'jquery/ui',
    'domReady!'
], function ($) {
    'use strict';

    $.widget('lof.dragDropUpload', {
        options: {},

        _create: function() {
            this.init();
        },

        init: function() {
            this.setupDragDropHandlers();
            this.enhanceUploadArea();
        },

        setupDragDropHandlers: function() {
            var self = this;
            var $dropZone = $('#fast-order-container');
            var $fileInput = $('#csv_file');

            // Prevent default drag behaviors
            $(document).on('dragenter dragover dragleave drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });

            // Highlight drop zone when dragging over it
            $dropZone.on('dragenter dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $dropZone.addClass('drag-over');
            });

            // Remove highlight when dragging leaves the drop zone
            $dropZone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $dropZone.removeClass('drag-over');
            });

            // Handle file drop
            $dropZone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $dropZone.removeClass('drag-over');

                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleFileUpload(files[0]);
                }
            });
        },

        enhanceUploadArea: function() {
            // Wait for Vue.js to render the upload area
            var checkForUploadArea = setInterval(function() {
                var $uploadButton = $('button[onclick*="openFileInput"]');
                if ($uploadButton.length > 0) {
                    clearInterval(checkForUploadArea);
                    
                    // Add drag and drop visual indicators
                    var $uploadSection = $uploadButton.closest('div');
                    if ($uploadSection.length > 0) {
                        $uploadSection.addClass('drag-drop-zone');
                        
                        // Add instructional text if not already present
                        if (!$uploadSection.find('.drag-drop-instructions').length) {
                            $uploadSection.append(
                                '<div class="drag-drop-instructions">' +
                                '<p>or drag and drop your CSV file here</p>' +
                                '</div>'
                            );
                        }
                    }
                }
            }, 500);

            // Stop checking after 10 seconds
            setTimeout(function() {
                clearInterval(checkForUploadArea);
            }, 10000);
        },

        handleFileUpload: function(file) {
            // Validate file type
            if (!this.isValidCSVFile(file)) {
                this.showErrorMessage('Please upload a valid CSV file.');
                return;
            }

            // Set the file to the hidden input
            var $fileInput = $('#csv_file')[0];
            if ($fileInput) {
                // Create a new FileList with the dropped file
                var dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                $fileInput.files = dataTransfer.files;

                // Trigger the existing upload functionality
                $fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        isValidCSVFile: function(file) {
            var validTypes = ['text/csv', 'application/csv', 'text/plain'];
            var validExtension = file.name.toLowerCase().endsWith('.csv');
            
            return validTypes.includes(file.type) || validExtension;
        },

        showErrorMessage: function(message) {
            // Create a temporary error message
            var $errorDiv = $('<div class="drag-drop-error">' + message + '</div>');
            $('#fast-order-container').prepend($errorDiv);
            
            // Remove after 3 seconds
            setTimeout(function() {
                $errorDiv.fadeOut(300, function() {
                    $errorDiv.remove();
                });
            }, 3000);
        }
    });

    // Auto-initialize when the Fast Order container is present
    $(document).ready(function() {
        var initDragDrop = function() {
            if ($('#fast-order-container').length > 0) {
                $('#fast-order-container').dragDropUpload();
            } else {
                // Retry after a short delay if container not found
                setTimeout(initDragDrop, 1000);
            }
        };
        
        initDragDrop();
    });

    return $.lof.dragDropUpload;
});