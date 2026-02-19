/**
 * Telebirr Payment Demo - Enhanced JavaScript
 * Designed by MIKEINTOSH SYSTEMS
 */

(function () {
    'use strict';

    // ===========================================
    // DOM Ready Handler
    // ===========================================
    document.addEventListener('DOMContentLoaded', function () {
        initializeQuickAmounts();
        initializeFormValidation();
        initializeAutoRefresh();
        initializeCopyButtons();
        initializeNotifications();
        initializeLoadingStates();
        initializeTooltips();
        initializeInputMasks();
        initializeThemeToggle();
        initializeAnimations();
    });

    // ===========================================
    // Quick Amount Buttons
    // ===========================================
    function initializeQuickAmounts() {
        const quickAmounts = document.querySelectorAll('.quick-amount');
        const amountInput = document.getElementById('amount');

        if (quickAmounts.length && amountInput) {
            quickAmounts.forEach(button => {
                button.addEventListener('click', function () {
                    const amount = this.dataset.amount;
                    amountInput.value = amount;

                    // Highlight selected amount
                    quickAmounts.forEach(btn => btn.classList.remove('selected'));
                    this.classList.add('selected');

                    // Trigger change event for validation
                    amountInput.dispatchEvent(new Event('change'));

                    // Show success notification
                    showNotification(`Amount set to ${amount} ETB`, 'success');
                });
            });
        }
    }

    // ===========================================
    // Form Validation
    // ===========================================
    function initializeFormValidation() {
        const paymentForm = document.querySelector('.payment-form');
        if (paymentForm) {
            paymentForm.addEventListener('submit', function (e) {
                const amount = document.getElementById('amount').value;
                const title = document.getElementById('title')?.value;

                if (amount <= 0) {
                    e.preventDefault();
                    showNotification('Please enter a valid amount greater than 0', 'error');
                    highlightElement(document.getElementById('amount'));
                }

                if (title && title.trim() === '') {
                    e.preventDefault();
                    showNotification('Please enter a product title', 'error');
                    highlightElement(document.getElementById('title'));
                }
            });
        }

        // Real-time validation
        const amountInput = document.getElementById('amount');
        if (amountInput) {
            amountInput.addEventListener('input', function () {
                validateAmount(this);
            });

            amountInput.addEventListener('blur', function () {
                formatAmount(this);
            });
        }

        const phoneInput = document.getElementById('customer_phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function () {
                validatePhone(this);
            });
        }

        const emailInput = document.getElementById('customer_email');
        if (emailInput) {
            emailInput.addEventListener('blur', function () {
                validateEmail(this);
            });
        }
    }

    function validateAmount(input) {
        const value = parseFloat(input.value);
        const errorElement = input.nextElementSibling;

        if (isNaN(value) || value <= 0) {
            input.classList.add('error');
            if (errorElement && errorElement.classList.contains('validation-error')) {
                errorElement.textContent = 'Please enter a valid amount';
            } else {
                const error = document.createElement('small');
                error.className = 'validation-error';
                error.style.color = 'var(--danger-color)';
                error.textContent = 'Please enter a valid amount';
                input.parentNode.insertBefore(error, input.nextSibling);
            }
        } else {
            input.classList.remove('error');
            if (errorElement && errorElement.classList.contains('validation-error')) {
                errorElement.remove();
            }
        }
    }

    function validatePhone(input) {
        const phone = input.value.replace(/\D/g, '');
        const errorElement = input.nextElementSibling;

        if (phone && (phone.length < 9 || phone.length > 10)) {
            input.classList.add('error');
            if (errorElement && errorElement.classList.contains('validation-error')) {
                errorElement.textContent = 'Phone number must be 9-10 digits';
            } else {
                const error = document.createElement('small');
                error.className = 'validation-error';
                error.style.color = 'var(--danger-color)';
                error.textContent = 'Phone number must be 9-10 digits';
                input.parentNode.insertBefore(error, input.nextSibling);
            }
        } else {
            input.classList.remove('error');
            if (errorElement && errorElement.classList.contains('validation-error')) {
                errorElement.remove();
            }
        }
    }

    function validateEmail(input) {
        const email = input.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const errorElement = input.nextElementSibling;

        if (email && !emailRegex.test(email)) {
            input.classList.add('error');
            if (errorElement && errorElement.classList.contains('validation-error')) {
                errorElement.textContent = 'Please enter a valid email';
            } else {
                const error = document.createElement('small');
                error.className = 'validation-error';
                error.style.color = 'var(--danger-color)';
                error.textContent = 'Please enter a valid email';
                input.parentNode.insertBefore(error, input.nextSibling);
            }
        } else {
            input.classList.remove('error');
            if (errorElement && errorElement.classList.contains('validation-error')) {
                errorElement.remove();
            }
        }
    }

    function formatAmount(input) {
        if (input.value) {
            let amount = parseFloat(input.value);
            if (!isNaN(amount)) {
                input.value = amount.toFixed(2);
            }
        }
    }

    function highlightElement(element) {
        element.style.transition = 'background-color 0.3s ease';
        element.style.backgroundColor = 'rgba(220, 53, 69, 0.1)';
        setTimeout(() => {
            element.style.backgroundColor = '';
        }, 1000);
    }

    // ===========================================
    // Auto Refresh
    // ===========================================
    function initializeAutoRefresh() {
        const autoRefresh = document.getElementById('auto-refresh');
        if (autoRefresh) {
            let refreshInterval;
            let countdown = 10;
            let countdownElement = document.getElementById('refresh-countdown');

            autoRefresh.addEventListener('change', function () {
                if (this.checked) {
                    if (!countdownElement) {
                        countdownElement = document.createElement('div');
                        countdownElement.id = 'refresh-countdown';
                        countdownElement.style.marginTop = '10px';
                        countdownElement.style.fontSize = '0.9rem';
                        countdownElement.style.color = 'var(--text-muted)';
                        autoRefresh.parentNode.appendChild(countdownElement);
                    }

                    refreshInterval = setInterval(() => {
                        countdown--;
                        if (countdownElement) {
                            countdownElement.textContent = `Refreshing in ${countdown} seconds...`;
                        }

                        if (countdown <= 0) {
                            countdown = 10;
                            location.reload();
                        }
                    }, 1000);
                } else {
                    clearInterval(refreshInterval);
                    if (countdownElement) {
                        countdownElement.remove();
                    }
                }
            });
        }
    }

    // ===========================================
    // Copy to Clipboard
    // ===========================================
    function initializeCopyButtons() {
        const codeBlocks = document.querySelectorAll('.code-block');

        codeBlocks.forEach(block => {
            // Create copy button
            const copyButton = document.createElement('button');
            copyButton.className = 'btn btn-small copy-btn';
            copyButton.innerHTML = '📋 Copy';
            copyButton.style.position = 'absolute';
            copyButton.style.top = '10px';
            copyButton.style.right = '10px';
            copyButton.style.zIndex = '10';
            copyButton.style.background = 'var(--bg-secondary)';
            copyButton.style.color = 'var(--text-primary)';
            copyButton.style.border = '1px solid var(--border-color)';

            // Create wrapper
            const wrapper = document.createElement('div');
            wrapper.style.position = 'relative';
            block.parentNode.insertBefore(wrapper, block);
            wrapper.appendChild(block);
            wrapper.appendChild(copyButton);

            // Add click handler
            copyButton.addEventListener('click', () => {
                copyToClipboard(block.textContent);
            });
        });
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copied to clipboard!', 'success');
        }).catch(err => {
            console.error('Failed to copy: ', err);
            showNotification('Failed to copy to clipboard', 'error');

            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showNotification('Copied to clipboard!', 'success');
            } catch (err) {
                showNotification('Failed to copy to clipboard', 'error');
            }
            document.body.removeChild(textarea);
        });
    }

    // ===========================================
    // Notifications
    // ===========================================
    function initializeNotifications() {
        // Create notification container
        const notificationContainer = document.createElement('div');
        notificationContainer.id = 'notification-container';
        notificationContainer.style.position = 'fixed';
        notificationContainer.style.top = '20px';
        notificationContainer.style.right = '20px';
        notificationContainer.style.zIndex = '9999';
        document.body.appendChild(notificationContainer);
    }

    function showNotification(message, type = 'info') {
        const container = document.getElementById('notification-container');
        if (!container) return;

        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.textContent = message;
        notification.style.marginBottom = '10px';
        notification.style.maxWidth = '300px';
        notification.style.animation = 'slideIn 0.3s ease';

        container.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode === container) {
                    container.removeChild(notification);
                }
            }, 300);
        }, 5000);
    }

    // ===========================================
    // Loading States
    // ===========================================
    function initializeLoadingStates() {
        const forms = document.querySelectorAll('form');

        forms.forEach(form => {
            form.addEventListener('submit', function () {
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    const originalText = submitButton.textContent;
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-small"></span> Processing...';

                    // Add spinner styles
                    const style = document.createElement('style');
                    style.textContent = `
                        .spinner-small {
                            display: inline-block;
                            width: 16px;
                            height: 16px;
                            border: 2px solid rgba(255,255,255,0.3);
                            border-radius: 50%;
                            border-top-color: #fff;
                            animation: spin 1s ease-in-out infinite;
                            margin-right: 8px;
                        }
                        
                        @keyframes spin {
                            to { transform: rotate(360deg); }
                        }
                    `;
                    document.head.appendChild(style);

                    // Re-enable after timeout (in case of errors)
                    setTimeout(() => {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalText;
                    }, 10000);
                }
            });
        });
    }

    // ===========================================
    // Tooltips
    // ===========================================
    function initializeTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');

        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', function (e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.dataset.tooltip;
                tooltip.style.position = 'absolute';
                tooltip.style.background = 'var(--bg-tertiary)';
                tooltip.style.color = 'var(--text-primary)';
                tooltip.style.padding = '5px 10px';
                tooltip.style.borderRadius = '4px';
                tooltip.style.fontSize = '0.875rem';
                tooltip.style.zIndex = '1000';
                tooltip.style.pointerEvents = 'none';
                tooltip.style.boxShadow = 'var(--box-shadow)';

                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';

                this.addEventListener('mouseleave', function () {
                    tooltip.remove();
                });
            });
        });
    }

    // ===========================================
    // Input Masks
    // ===========================================
    function initializeInputMasks() {
        const phoneInput = document.getElementById('customer_phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 0) {
                    if (value.length <= 3) {
                        value = value;
                    } else if (value.length <= 6) {
                        value = value.slice(0, 3) + '-' + value.slice(3);
                    } else {
                        value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6, 10);
                    }
                    e.target.value = value;
                }
            });
        }
    }

    // ===========================================
    // Theme Toggle
    // ===========================================
        /*   
    function initializeThemeToggle() {
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);

                showNotification(`Switched to ${newTheme} mode`, 'info');
            });
        }
    }
        */
    
    // ===========================================
    // Animations
    // ===========================================
    function initializeAnimations() {
        // Animate elements on scroll
        const animatedElements = document.querySelectorAll('.card, .status-item, .alert');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        animatedElements.forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            element.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(element);
        });
    }

    // ===========================================
    // Export functions for global use
    // ===========================================
    window.TelebirrPayment = {
        showNotification,
        copyToClipboard,
        validateAmount,
        validatePhone,
        validateEmail
    };
})();