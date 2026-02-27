/**
 * NetSuite Product Manager - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // File upload progress indicator
        $('form[enctype="multipart/form-data"]').on('submit', function() {
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
            var originalText = $submitBtn.val() || $submitBtn.text();
            
            // Disable submit button
            $submitBtn.prop('disabled', true);
            
            // Change button text
            if ($submitBtn.is('button')) {
                $submitBtn.html('⏳ Uploading... Please Wait');
            } else {
                $submitBtn.val('⏳ Uploading... Please Wait');
            }
            
            // Show progress message
            var progressHtml = '<div class="npm-upload-progress active">' +
                '<strong>⏳ Processing file...</strong><br>' +
                '<span style="font-size:12px;">Large files may take 1-2 minutes. Please do not refresh the page.</span>' +
                '</div>';
            
            if ($form.find('.npm-upload-progress').length === 0) {
                $form.append(progressHtml);
            }
        });
        
        // Generate export confirmation
        $('button[name="npm_generate_export"]').on('click', function(e) {
            if (!confirm('Generate export queue? This will:\n\n' +
                '• Create all child SKU variations\n' +
                '• Link NetSuite internal IDs\n' +
                '• Mark discontinued products\n' +
                '• Populate export queue\n\n' +
                'This may take 1-2 minutes. Continue?')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Download confirmation for large exports
        $('a[href*="npm_download=netsuite_update"]').on('click', function(e) {
            var productCount = parseInt($(this).closest('.npm-card').find('p:last').text().replace(/[^0-9]/g, ''));
            
            if (productCount > 10000) {
                if (!confirm('This export contains ' + productCount.toLocaleString() + ' products.\n\n' +
                    'The download may take 30-60 seconds. Continue?')) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading message
                var $loadingMsg = $('<div class="npm-upload-progress active" style="margin-top:15px;">' +
                    '<strong>⏳ Preparing download...</strong><br>' +
                    '<span style="font-size:12px;">This may take up to 60 seconds. Please wait.</span>' +
                    '</div>');
                
                $(this).after($loadingMsg);
                
                // Remove loading message after 3 seconds (download should have started)
                setTimeout(function() {
                    $loadingMsg.fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        });
        
        // Auto-dismiss notices after 5 seconds
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut();
        }, 5000);
        
        // Confirm before leaving page with unsaved changes
        var formChanged = false;
        
        $('form input, form select, form textarea').on('change', function() {
            formChanged = true;
        });
        
        $('form').on('submit', function() {
            formChanged = false;
        });
        
        $(window).on('beforeunload', function() {
            if (formChanged) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
    });
    
})(jQuery);