// Payment handling functions
async function initializePayment(packageData) {
    try {
        const response = await fetch('/Shop/process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(packageData)
        });

        const data = await response.json();
        
        if (data.status === 'error') {
            showError(data.message);
            return;
        }

        // TODO: Initialize Cashfree payment
        // This is where we'll add the Cashfree SDK initialization
        // For now, just show a placeholder message
        showSuccess('Payment processing will be implemented soon!');
        
    } catch (error) {
        console.error('Payment initialization failed:', error);
        showError('Failed to initialize payment. Please try again.');
    }
}

// UI helper functions
function showError(message) {
    const errorDiv = document.getElementById('payment-error');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }
}

function showSuccess(message) {
    const successDiv = document.getElementById('payment-success');
    if (successDiv) {
        successDiv.textContent = message;
        successDiv.style.display = 'block';
        setTimeout(() => {
            successDiv.style.display = 'none';
        }, 5000);
    }
}

// Event listeners for payment buttons
document.addEventListener('DOMContentLoaded', () => {
    const paymentButtons = document.querySelectorAll('.payment-button');
    
    paymentButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            
            const packageData = {
                package: button.dataset.package,
                amount: parseFloat(button.dataset.amount),
                coins: parseInt(button.dataset.coins, 10),
                tickets: parseInt(button.dataset.tickets, 10)
            };

            initializePayment(packageData);
        });
    });
}); 