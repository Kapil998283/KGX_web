// Package configurations
const packages = {
    coins: {
        starter: {
            name: "Starter",
            price: 10,
            amount: 200,
            validity: 30
        },
        popular: {
            name: "Popular",
            price: 100,
            amount: 2000,
            validity: 60
        },
        premium: {
            name: "Premium",
            price: 250,
            amount: 5000,
            validity: 90
        }
    },
    tickets: {
        starter: {
            name: "Starter",
            price: 10,
            amount: 1,
            validity: 30
        },
        popular: {
            name: "Popular",
            price: 100,
            amount: 10,
            validity: 60
        },
        premium: {
            name: "Premium",
            price: 500,
            amount: 50,
            validity: 90
        }
    }
};

// Toggle functionality
const checkbox = document.getElementById("checkbox");
const cards = document.querySelectorAll('.card');

// Update card content based on type (coins/tickets)
function updateCards(isTickets) {
    const type = isTickets ? 'tickets' : 'coins';
    const packageType = packages[type];
    
    // Update starter package
    const starterCard = document.querySelector('.card:nth-child(1)');
    starterCard.querySelector('.price').textContent = `₹${packageType.starter.price}`;
    starterCard.querySelector('li:nth-child(3)').textContent = 
        isTickets ? `${packageType.starter.amount} Tickets` : `${packageType.starter.amount.toLocaleString()} Coins`;
    starterCard.querySelector('button').onclick = () => 
        showPaymentModal(packageType.starter.name, packageType.starter.price, type, packageType.starter.amount);

    // Update popular package
    const popularCard = document.querySelector('.card:nth-child(2)');
    popularCard.querySelector('.price').textContent = `₹${packageType.popular.price}`;
    popularCard.querySelector('li:nth-child(3)').textContent = 
        isTickets ? `${packageType.popular.amount} Tickets` : `${packageType.popular.amount.toLocaleString()} Coins`;
    popularCard.querySelector('button').onclick = () => 
        showPaymentModal(packageType.popular.name, packageType.popular.price, type, packageType.popular.amount);

    // Update premium package
    const premiumCard = document.querySelector('.card:nth-child(3)');
    premiumCard.querySelector('.price').textContent = `₹${packageType.premium.price}`;
    premiumCard.querySelector('li:nth-child(3)').textContent = 
        isTickets ? `${packageType.premium.amount} Tickets` : `${packageType.premium.amount.toLocaleString()} Coins`;
    premiumCard.querySelector('button').onclick = () => 
        showPaymentModal(packageType.premium.name, packageType.premium.price, type, packageType.premium.amount);

    // Update validity text for all cards
    cards.forEach((card, index) => {
        const packageKey = ['starter', 'popular', 'premium'][index];
        card.querySelector('li:nth-child(4)').textContent = 
            `Valid for ${packageType[packageKey].validity} days`;
    });
}

// Toggle between coins and tickets
checkbox.addEventListener('change', function() {
    const isTickets = this.checked;
    updateCards(isTickets);
});

// Initialize with coins view
updateCards(false);

// Payment modal functionality
function showPaymentModal(packageName, amount, type, quantity) {
    const modal = document.getElementById('paymentModal');
    document.getElementById('packageName').textContent = packageName;
    document.getElementById('packageType').textContent = type.charAt(0).toUpperCase() + type.slice(1);
    document.getElementById('packageQuantity').textContent = quantity.toLocaleString();
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
        const type = document.getElementById('packageType').textContent.toLowerCase();
        const quantity = document.getElementById('packageQuantity').textContent.replace(',', '');

        const response = await fetch('/Shop/process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                package: packageName,
                amount: parseFloat(amount),
                type: type,
                quantity: parseInt(quantity)
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
                type: button.dataset.type,
                quantity: parseInt(button.dataset.quantity, 10)
            };

            initializePayment(packageData);
        });
    });
}); 