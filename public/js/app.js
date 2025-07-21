// Global variables
let authToken = localStorage.getItem('authToken');
let currentUser = null;

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    if (authToken) {
        loadUserProfile();
    } else {
        showLoginForm();
    }
});

// Authentication Functions
async function login(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: formData.get('username'),
                password: formData.get('password')
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            authToken = data.token;
            currentUser = data.user;
            localStorage.setItem('authToken', authToken);
            showDashboard();
            showToast('Login successful!', 'success');
        } else {
            showToast(data.error || 'Login failed', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

async function register(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        const response = await fetch('/api/auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: formData.get('username'),
                email: formData.get('email'),
                password: formData.get('password')
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast('Registration successful! Please login.', 'success');
            showLoginForm();
            document.getElementById('username').value = formData.get('username');
        } else {
            showToast(data.error || 'Registration failed', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

async function loadUserProfile() {
    try {
        const response = await fetch('/api/auth/profile', {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            currentUser = data.user;
            showDashboard();
        } else {
            logout();
        }
    } catch (error) {
        logout();
    }
}

function logout() {
    authToken = null;
    currentUser = null;
    localStorage.removeItem('authToken');
    showLoginForm();
}

// UI Navigation Functions
function showLoginForm() {
    document.getElementById('loginForm').classList.remove('hidden');
    document.getElementById('registerForm').classList.add('hidden');
    document.getElementById('dashboard').classList.add('hidden');
}

function showRegisterForm() {
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registerForm').classList.remove('hidden');
    document.getElementById('dashboard').classList.add('hidden');
}

function showDashboard() {
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registerForm').classList.add('hidden');
    document.getElementById('dashboard').classList.remove('hidden');
    
    if (currentUser) {
        document.getElementById('userInfo').textContent = `Welcome, ${currentUser.username}`;
    }
    
    // Load default tab content
    showTab('sendMessage');
    loadContacts();
    loadMessageHistory();
}

function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Show selected tab
    document.getElementById(tabName).classList.remove('hidden');
    
    // Update tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-indigo-500', 'text-indigo-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    event.target.classList.remove('border-transparent', 'text-gray-500');
    event.target.classList.add('border-indigo-500', 'text-indigo-600');
}

// Message Functions
function toggleMessageType() {
    const messageType = document.querySelector('input[name="messageType"]:checked').value;
    
    if (messageType === 'single') {
        document.getElementById('singleMessageForm').classList.remove('hidden');
        document.getElementById('bulkMessageForm').classList.add('hidden');
    } else {
        document.getElementById('singleMessageForm').classList.add('hidden');
        document.getElementById('bulkMessageForm').classList.remove('hidden');
    }
}

async function sendSingleMessage(event) {
    event.preventDefault();
    
    const phoneNumber = document.getElementById('singlePhone').value;
    const message = document.getElementById('singleMessage').value;
    
    try {
        const response = await fetch('/api/messages/send', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${authToken}`,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                phone_number: phoneNumber,
                message: message
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast('Message sent successfully!', 'success');
            document.getElementById('singlePhone').value = '';
            document.getElementById('singleMessage').value = '';
            loadMessageHistory();
        } else {
            showToast(data.error || 'Failed to send message', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

async function sendBulkMessage(event) {
    event.preventDefault();
    
    const phonesText = document.getElementById('bulkPhones').value;
    const message = document.getElementById('bulkMessage').value;
    
    // Parse phone numbers
    const phoneNumbers = phonesText
        .split(/[\n,]/)
        .map(phone => phone.trim())
        .filter(phone => phone.length > 0);
    
    if (phoneNumbers.length === 0) {
        showToast('Please enter at least one phone number', 'error');
        return;
    }
    
    if (phoneNumbers.length > 100) {
        showToast('Maximum 100 recipients allowed per batch', 'error');
        return;
    }
    
    try {
        const response = await fetch('/api/messages/send-bulk', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${authToken}`,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                phone_numbers: phoneNumbers,
                message: message
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast(`Bulk message sent! ${data.successful} successful, ${data.failed} failed`, 'success');
            document.getElementById('bulkPhones').value = '';
            document.getElementById('bulkMessage').value = '';
            loadMessageHistory();
        } else {
            showToast(data.error || 'Failed to send bulk message', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

// Contact Functions
async function loadContacts() {
    try {
        const response = await fetch('/api/contacts', {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            displayContacts(data.contacts);
        }
    } catch (error) {
        console.error('Failed to load contacts:', error);
    }
}

function displayContacts(contacts) {
    const container = document.getElementById('contactsList');
    
    if (contacts.length === 0) {
        container.innerHTML = '<p class="text-gray-500 col-span-full text-center">No contacts found. Add some contacts to get started!</p>';
        return;
    }
    
    container.innerHTML = contacts.map(contact => `
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="font-medium text-gray-900">${contact.name || 'Unnamed'}</h3>
                    <p class="text-sm text-gray-600">${contact.phone_number}</p>
                    <p class="text-xs text-gray-400">Added: ${new Date(contact.created_at).toLocaleDateString()}</p>
                </div>
                <button onclick="deleteContact(${contact.id})" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function showAddContactForm() {
    document.getElementById('addContactForm').classList.remove('hidden');
}

function hideAddContactForm() {
    document.getElementById('addContactForm').classList.add('hidden');
    document.getElementById('contactName').value = '';
    document.getElementById('contactPhone').value = '';
}

async function addContact(event) {
    event.preventDefault();
    
    const name = document.getElementById('contactName').value;
    const phone = document.getElementById('contactPhone').value;
    
    try {
        const response = await fetch('/api/contacts', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${authToken}`,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: name,
                phone_number: phone
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast('Contact added successfully!', 'success');
            hideAddContactForm();
            loadContacts();
        } else {
            showToast(data.error || 'Failed to add contact', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

async function deleteContact(contactId) {
    if (!confirm('Are you sure you want to delete this contact?')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/contacts/${contactId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (response.ok) {
            showToast('Contact deleted successfully!', 'success');
            loadContacts();
        } else {
            const data = await response.json();
            showToast(data.error || 'Failed to delete contact', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

function showUploadForm() {
    document.getElementById('uploadForm').classList.remove('hidden');
}

function hideUploadForm() {
    document.getElementById('uploadForm').classList.add('hidden');
    document.getElementById('csvFile').value = '';
}

async function uploadContacts(event) {
    event.preventDefault();
    
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Please select a file', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        const response = await fetch('/api/contacts/upload', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${authToken}`
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast(`Upload completed! ${data.successful} contacts added, ${data.failed} failed`, 'success');
            hideUploadForm();
            loadContacts();
        } else {
            showToast(data.error || 'Failed to upload contacts', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

async function loadContactsForBulk() {
    try {
        const response = await fetch('/api/contacts', {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            const phoneNumbers = data.contacts.map(contact => contact.phone_number).join('\n');
            document.getElementById('bulkPhones').value = phoneNumbers;
            showToast(`Loaded ${data.contacts.length} contacts`, 'success');
        }
    } catch (error) {
        showToast('Failed to load contacts', 'error');
    }
}

// Message History Functions
async function loadMessageHistory() {
    try {
        const response = await fetch('/api/messages/history', {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            displayMessageHistory(data.messages);
        }
    } catch (error) {
        console.error('Failed to load message history:', error);
    }
}

function displayMessageHistory(messages) {
    const container = document.getElementById('messageHistory');
    
    if (messages.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center">No message history found.</p>';
        return;
    }
    
    container.innerHTML = messages.map(message => {
        const statusColor = {
            'completed': 'text-green-600',
            'processing': 'text-yellow-600',
            'failed': 'text-red-600',
            'pending': 'text-gray-600'
        }[message.status] || 'text-gray-600';
        
        return `
            <div class="border border-gray-200 rounded-lg p-4 mb-4">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-medium text-gray-900">Message #${message.id}</h3>
                    <span class="${statusColor} text-sm font-medium">${message.status.toUpperCase()}</span>
                </div>
                <p class="text-gray-700 mb-2">${message.message_text}</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
                    <div>
                        <span class="font-medium">Recipients:</span> ${message.total_recipients}
                    </div>
                    <div>
                        <span class="font-medium">Successful:</span> ${message.successful_sends}
                    </div>
                    <div>
                        <span class="font-medium">Failed:</span> ${message.failed_sends}
                    </div>
                    <div>
                        <span class="font-medium">Sent:</span> ${new Date(message.created_at).toLocaleDateString()}
                    </div>
                </div>
                <div class="mt-2">
                    <button onclick="viewMessageDetails(${message.id})" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        View Details
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

async function viewMessageDetails(messageId) {
    try {
        const response = await fetch(`/api/messages/${messageId}/status`, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            showMessageDetailsModal(data);
        }
    } catch (error) {
        showToast('Failed to load message details', 'error');
    }
}

function showMessageDetailsModal(data) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
    modal.innerHTML = `
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Message Details</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mb-4">
                    <p class="text-gray-700">${data.message.message_text}</p>
                    <p class="text-sm text-gray-500 mt-2">Sent: ${new Date(data.message.created_at).toLocaleString()}</p>
                </div>
                <div class="mb-4">
                    <h4 class="font-medium mb-2">Delivery Status Summary:</h4>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
                        ${Object.entries(data.status_summary).map(([status, count]) => `
                            <div class="bg-gray-50 p-2 rounded">
                                <div class="font-medium">${status}</div>
                                <div class="text-gray-600">${count}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                <div class="max-h-60 overflow-y-auto">
                    <h4 class="font-medium mb-2">Recipients:</h4>
                    <div class="space-y-2">
                        ${data.recipients.map(recipient => `
                            <div class="flex justify-between items-center py-2 px-3 bg-gray-50 rounded">
                                <span>${recipient.phone_number}</span>
                                <span class="text-sm ${
                                    recipient.delivery_status === 'sent' || recipient.delivery_status === 'delivered' 
                                        ? 'text-green-600' 
                                        : recipient.delivery_status === 'failed' 
                                        ? 'text-red-600' 
                                        : 'text-gray-600'
                                }">${recipient.delivery_status}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Toast Notification Functions
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const icon = document.getElementById('toastIcon');
    const messageEl = document.getElementById('toastMessage');
    
    messageEl.textContent = message;
    
    // Set icon and color based on type
    const iconMap = {
        'success': 'fas fa-check-circle text-green-500',
        'error': 'fas fa-exclamation-circle text-red-500',
        'warning': 'fas fa-exclamation-triangle text-yellow-500',
        'info': 'fas fa-info-circle text-blue-500'
    };
    
    icon.className = iconMap[type] || iconMap['info'];
    
    toast.classList.remove('hidden');
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        hideToast();
    }, 5000);
}

function hideToast() {
    document.getElementById('toast').classList.add('hidden');
}