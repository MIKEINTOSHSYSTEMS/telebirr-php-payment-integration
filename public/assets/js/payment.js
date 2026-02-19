/**
 * Telebirr Payment Demo - JavaScript
 */

// Quick amount buttons
document.addEventListener('DOMContentLoaded', function() {
    const quickAmounts = document.querySelectorAll('.quick-amount');
    const amountInput = document.getElementById('amount');
    
    if (quickAmounts.length && amountInput) {
        quickAmounts.forEach(button => {
            button.addEventListener('click', function() {
                const amount = this.dataset.amount;
                amountInput.value = amount;
                
                // Highlight selected amount
                quickAmounts.forEach(btn => btn.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
    }
    
    // Form validation
    const paymentForm = document.querySelector('.payment-form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            const amount = document.getElementById('amount').value;
            
            if (amount <= 0) {
                e.preventDefault();
                showNotification('Please enter a valid amount greater than 0', 'error');
            }
        });
    }
    
    // Auto-refresh status on query page
    const autoRefresh = document.getElementById('auto-refresh');
    if (autoRefresh) {
        let refreshInterval;
        
        autoRefresh.addEventListener('change', function() {
            if (this.checked) {
                refreshInterval = setInterval(() => {
                    location.reload();
                }, 10000); // Refresh every 10 seconds
            } else {
                clearInterval(refreshInterval);
            }
        });
    }
});

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.maxWidth = '300px';
    notification.style.animation = 'slideIn 0.3s ease';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copied to clipboard!', 'success');
    }).catch(() => {
        showNotification('Failed to copy', 'error');
    });
}

// Add copy buttons to code blocks
document.addEventListener('DOMContentLoaded', function() {
    const codeBlocks = document.querySelectorAll('.code-block');
    
    codeBlocks.forEach(block => {
        const copyButton = document.createElement('button');
        copyButton.className = 'btn btn-small';
        copyButton.textContent = 'Copy';
        copyButton.style.position = 'absolute';
        copyButton.style.top = '10px';
        copyButton.style.right = '10px';
        
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        block.parentNode.insertBefore(wrapper, block);
        wrapper.appendChild(block);
        wrapper.appendChild(copyButton);
        
        copyButton.addEventListener('click', () => {
            copyToClipboard(block.textContent);
        });
    });
});

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .quick-amount.selected {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
`;
document.head.appendChild(style);