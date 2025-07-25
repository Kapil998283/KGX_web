// Multi-Step Authentication JavaScript

document.addEventListener('DOMContentLoaded', function() {
    
    // Password strength checker
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const strengthFill = document.querySelector('.strength-fill');
    const strengthText = document.querySelector('.strength-text');
    const passwordMatch = document.querySelector('.password-match');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            updatePasswordStrength(strength);
            
            if (confirmPasswordInput) {
                checkPasswordMatch();
            }
        });
    }
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
    
    function checkPasswordStrength(password) {
        let score = 0;
        const checks = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            numbers: /\d/.test(password),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };
        
        Object.values(checks).forEach(check => {
            if (check) score++;
        });
        
        if (score < 3) return 'weak';
        if (score < 5) return 'medium';
        return 'strong';
    }
    
    function updatePasswordStrength(strength) {
        if (!strengthFill || !strengthText) return;
        
        strengthFill.className = 'strength-fill ' + strength;
        
        const messages = {
            weak: 'Weak',
            medium: 'Medium',
            strong: 'Strong'
        };
        
        strengthText.textContent = 'Password Strength: ' + messages[strength];
    }
    
    function checkPasswordMatch() {
        if (!passwordInput || !confirmPasswordInput || !passwordMatch) return;
        
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (confirmPassword.length > 0) {
            if (password === confirmPassword) {
                passwordMatch.classList.add('show');
                passwordMatch.querySelector('.match-text').textContent = 'Passwords match';
                passwordMatch.querySelector('.match-icon').name = 'checkmark-circle-outline';
            } else {
                passwordMatch.classList.add('show');
                passwordMatch.querySelector('.match-text').textContent = 'Passwords do not match';
                passwordMatch.querySelector('.match-icon').name = 'close-circle-outline';
                passwordMatch.querySelector('.match-icon').style.color = 'var(--error-color)';
                passwordMatch.querySelector('.match-text').style.color = 'var(--error-color)';
            }
        } else {
            passwordMatch.classList.remove('show');
        }
    }
    
    // Password toggle functionality
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('ion-icon');
            
            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.name = 'eye-off-outline';
            } else {
                targetInput.type = 'password';
                icon.name = 'eye-outline';
            }
        });
    });
    
    // Form validation enhancement
    const forms = document.querySelectorAll('.auth-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredInputs = form.querySelectorAll('input[required]');
            let isValid = true;
            
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = 'var(--error-color)';
                    input.addEventListener('input', function() {
                        this.style.borderColor = '';
                    }, { once: true });
                }
            });
            
            // Check password match for registration forms
            if (passwordInput && confirmPasswordInput) {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    isValid = false;
                    confirmPasswordInput.style.borderColor = 'var(--error-color)';
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                showErrorMessage('Please fill in all required fields correctly.');
            }
        });
    });
    
    // Input animations and focus handling
    const inputs = document.querySelectorAll('.form-group input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
        
        // Add floating label effect
        if (input.value) {
            input.parentElement.classList.add('focused');
        }
    });
    
    // Progress bar animation
    const progressFill = document.querySelector('.progress-fill');
    if (progressFill) {
        setTimeout(() => {
            progressFill.style.transition = 'width 1s ease-in-out';
        }, 100);
    }
    
    // Step indicator animations
    const stepIndicators = document.querySelectorAll('.step-indicator');
    stepIndicators.forEach((indicator, index) => {
        setTimeout(() => {
            indicator.style.opacity = '1';
            indicator.style.transform = 'translateY(0)';
        }, index * 200);
    });
    
    // Utility functions
    function showErrorMessage(message) {
        // Remove existing error messages
        const existingError = document.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Create new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = `
            <ion-icon name="alert-circle-outline"></ion-icon>
            ${message}
        `;
        
        // Insert at the top of the form
        const form = document.querySelector('.auth-form');
        if (form) {
            form.parentNode.insertBefore(errorDiv, form);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }
    }
    
    function showSuccessMessage(message) {
        // Remove existing success messages
        const existingSuccess = document.querySelector('.success-message');
        if (existingSuccess) {
            existingSuccess.remove();
        }
        
        // Create new success message
        const successDiv = document.createElement('div');
        successDiv.className = 'success-message';
        successDiv.innerHTML = `
            <ion-icon name="checkmark-circle-outline"></ion-icon>
            ${message}
        `;
        
        // Insert at the top of the form
        const form = document.querySelector('.auth-form');
        if (form) {
            form.parentNode.insertBefore(successDiv, form);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                successDiv.remove();
            }, 3000);
        }
    }
    
    // Social button hover effects
    const socialButtons = document.querySelectorAll('.social-btn');
    socialButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.05)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Game card hover effects (for game selection page)
    const gameCards = document.querySelectorAll('.game-card');
    gameCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            if (!this.classList.contains('selected')) {
                this.style.transform = 'translateY(0) scale(1)';
            }
        });
    });
    
    // Button loading states
    function setButtonLoading(button, loading = true) {
        if (loading) {
            button.disabled = true;
            button.style.opacity = '0.7';
            const originalContent = button.innerHTML;
            button.dataset.originalContent = originalContent;
            button.innerHTML = `
                <div class="loading-spinner"></div>
                <span>Processing...</span>
            `;
        } else {
            button.disabled = false;
            button.style.opacity = '1';
            button.innerHTML = button.dataset.originalContent || button.innerHTML;
        }
    }
    
    // Add loading spinner CSS if not already present
    if (!document.querySelector('#loading-spinner-styles')) {
        const style = document.createElement('style');
        style.id = 'loading-spinner-styles';
        style.textContent = `
            .loading-spinner {
                width: 20px;
                height: 20px;
                border: 2px solid rgba(255, 255, 255, 0.3);
                border-top: 2px solid white;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Form submission with loading states
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                setButtonLoading(submitButton, true);
                
                // Reset loading state after form submission
                setTimeout(() => {
                    setButtonLoading(submitButton, false);
                }, 3000);
            }
        });
    });
    
    // Smooth scrolling for navigation
    const navigationLinks = document.querySelectorAll('a[href^="#"]');
    navigationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Auto-focus first input
    const firstInput = document.querySelector('.auth-form input:not([type="hidden"])');
    if (firstInput) {
        setTimeout(() => {
            firstInput.focus();
        }, 500);
    }
    
    // Keyboard navigation enhancements
    document.addEventListener('keydown', function(e) {
        // Enter key navigation
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
            const form = e.target.closest('form');
            if (form) {
                const inputs = Array.from(form.querySelectorAll('input:not([type="hidden"])'));
                const currentIndex = inputs.indexOf(e.target);
                
                if (currentIndex < inputs.length - 1) {
                    e.preventDefault();
                    inputs[currentIndex + 1].focus();
                }
            }
        }
        
        // Escape key to clear focus
        if (e.key === 'Escape') {
            document.activeElement.blur();
        }
    });
    
    // Phone number formatting (if international phone input is not used)
    const phoneInputs = document.querySelectorAll('input[type="tel"]:not(.phone-input)');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Format as (XXX) XXX-XXXX for US numbers
            if (value.length >= 6) {
                value = `(${value.slice(0, 3)}) ${value.slice(3, 6)}-${value.slice(6, 10)}`;
            } else if (value.length >= 3) {
                value = `(${value.slice(0, 3)}) ${value.slice(3)}`;
            }
            
            e.target.value = value;
        });
    });
    
    // Initialize tooltips (if using a tooltip library)
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.cssText = `
                position: absolute;
                background: var(--card-bg);
                color: var(--text-light);
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 1000;
                border: 1px solid rgba(255, 255, 255, 0.1);
                box-shadow: var(--shadow-lg);
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
            
            this.addEventListener('mouseleave', function() {
                tooltip.remove();
            }, { once: true });
        });
    });
});

// Utility function to check if element is in viewport
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Animate elements when they come into view
function animateOnScroll() {
    const elements = document.querySelectorAll('.auth-container, .game-card, .step-indicator');
    elements.forEach(element => {
        if (isInViewport(element)) {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }
    });
}

// Throttled scroll listener
let scrollTimeout;
window.addEventListener('scroll', function() {
    if (scrollTimeout) {
        clearTimeout(scrollTimeout);
    }
    scrollTimeout = setTimeout(animateOnScroll, 100);
});

// Initial animation check
setTimeout(animateOnScroll, 100);
