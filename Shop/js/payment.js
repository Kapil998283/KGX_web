// Toggle functionality
const checkbox = document.getElementById("checkbox");
const cards = document.querySelectorAll('.card');

// Store original values
const originalValues = {
    basic: {
        coins: 1000,
        tickets: 100,
        price: 199
    },
    professional: {
        coins: 2500,
        tickets: 250,
        price: 499
    },
    master: {
        coins: 5000,
        tickets: 500,
        price: 999
    }
};

// Toggle between coins and tickets
checkbox.addEventListener('change', function() {
    const isTickets = this.checked;
    
    cards.forEach(card => {
        const priceElement = card.querySelector('.price');
        const coinsElement = card.querySelector('li:nth-child(3)');
        const ticketsElement = card.querySelector('li:nth-child(4)');
        
        if (isTickets) {
            // Show tickets more prominently
            coinsElement.style.opacity = '0.7';
            ticketsElement.style.opacity = '1';
            ticketsElement.style.fontWeight = '600';
            coinsElement.style.fontWeight = 'normal';
        } else {
            // Show coins more prominently
            coinsElement.style.opacity = '1';
            ticketsElement.style.opacity = '0.7';
            coinsElement.style.fontWeight = '600';
            ticketsElement.style.fontWeight = 'normal';
        }
    });
});

// Payment modal functionality
function showPaymentModal(packageName, amount, coins, tickets) {
    const modal = document.getElementById('paymentModal');
    document.getElementById('packageName').textContent = packageName;
    document.getElementById('packageCoins').textContent = coins.toLocaleString();
    document.getElementById('packageTickets').textContent = tickets.toLocaleString();
    document.getElementById('packageAmount').textContent = '₹' + amount.toLocaleString();
    
    modal.style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('paymentModal');
    if (event.target == modal) {
        closePaymentModal();
    }
}

// Initialize payment
async function initializePayment() {
    try {
        const packageName = document.getElementById('packageName').textContent;
        const amount = document.getElementById('packageAmount').textContent.replace('₹', '').replace(',', '');
        const coins = document.getElementById('packageCoins').textContent.replace(',', '');
        const tickets = document.getElementById('packageTickets').textContent.replace(',', '');

        const response = await fetch('/Shop/process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                package: packageName,
                amount: parseFloat(amount),
                coins: parseInt(coins),
                tickets: parseInt(tickets)
            })
        });

        const data = await response.json();
        
        if (data.status === 'error') {
            alert(data.message);
            return;
        }

        // TODO: Initialize Cashfree payment
        alert('Payment gateway integration pending. This feature will be available soon!');
        closePaymentModal();
        
    } catch (error) {
        console.error('Payment initialization failed:', error);
        alert('Failed to initialize payment. Please try again.');
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