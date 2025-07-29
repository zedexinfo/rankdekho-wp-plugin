/* RankDekho Payment Gateway Admin JavaScript */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initTabs();
        bindEvents();
    });
    
    /**
     * Initialize tab functionality
     */
    function initTabs() {
        $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href');
            
            // Remove active class from all tabs and content
            $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
            $('.tab-content').removeClass('active');
            
            // Add active class to clicked tab and corresponding content
            $(this).addClass('nav-tab-active');
            $(target).addClass('active');
        });
    }
    
    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Auto-refresh logs
        if ($('#logs').length) {
            setInterval(refreshLogs, 30000); // Refresh every 30 seconds
        }
    }
    
    /**
     * Refresh logs content
     */
    function refreshLogs() {
        if (!$('#logs').hasClass('active')) {
            return;
        }
        
        $.ajax({
            url: rankdekho_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'rankdekho_refresh_logs',
                nonce: rankdekho_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#debug-logs').html(response.data.debug_logs);
                    $('#auth-logs').html(response.data.auth_logs);
                }
            }
        });
    }
    
    /**
     * Show results message
     */
    function showResults(message, type) {
        var $results = $('#tools-results');
        $results.removeClass('success error').addClass(type).addClass('show');
        $results.html('<p>' + message + '</p>');
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $results.removeClass('show');
        }, 5000);
    }
    
    // Global functions for admin tools
    window.regenerateApiKey = function() {
        if (!confirm('Are you sure you want to regenerate the API key? This will invalidate the current key.')) {
            return;
        }
        
        $.ajax({
            url: rankdekho_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'rankdekho_regenerate_api_key',
                nonce: rankdekho_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('input[name="rankdekho_pg_api_key"]').val(response.data.api_key);
                    showResults('API key regenerated successfully!', 'success');
                } else {
                    showResults('Error: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showResults('Error: Failed to regenerate API key', 'error');
            }
        });
    };
    
    window.regenerateEncryptionKey = function() {
        if (!confirm('WARNING: Regenerating the encryption key will invalidate all existing hash tokens. Are you sure?')) {
            return;
        }
        
        $.ajax({
            url: rankdekho_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'rankdekho_regenerate_encryption_key',
                nonce: rankdekho_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('input[name="rankdekho_pg_encryption_key"]').val(response.data.encryption_key);
                    showResults('Encryption key regenerated successfully!', 'success');
                } else {
                    showResults('Error: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showResults('Error: Failed to regenerate encryption key', 'error');
            }
        });
    };
    
    window.cleanupExpiredData = function() {
        showResults('Running cleanup...', 'success');
        
        $.ajax({
            url: rankdekho_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'rankdekho_cleanup_expired_data',
                nonce: rankdekho_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showResults('Cleanup completed successfully! ' + response.data.message, 'success');
                } else {
                    showResults('Error: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showResults('Error: Failed to run cleanup', 'error');
            }
        });
    };
    
    window.testApiConnection = function() {
        showResults('Testing API connection...', 'success');
        
        $.ajax({
            url: rankdekho_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'rankdekho_test_api',
                nonce: rankdekho_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var message = 'API test completed:<br>';
                    message += 'Sync User endpoint: ' + (response.data.sync_user ? 'OK' : 'FAILED') + '<br>';
                    message += 'Process Payment endpoint: ' + (response.data.process_payment ? 'OK' : 'FAILED');
                    showResults(message, response.data.sync_user && response.data.process_payment ? 'success' : 'error');
                } else {
                    showResults('Error: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showResults('Error: Failed to test API connection', 'error');
            }
        });
    };
    
    window.clearLogs = function(type) {
        if (!confirm('Are you sure you want to clear the ' + type + ' logs?')) {
            return;
        }
        
        $.ajax({
            url: rankdekho_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'rankdekho_clear_logs',
                log_type: type,
                nonce: rankdekho_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (type === 'debug') {
                        $('#debug-logs').html('<p>No debug logs available.</p>');
                    } else {
                        $('#auth-logs').html('<p>No authentication logs available.</p>');
                    }
                    showResults('Logs cleared successfully!', 'success');
                } else {
                    showResults('Error: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showResults('Error: Failed to clear logs', 'error');
            }
        });
    };
    
})(jQuery);