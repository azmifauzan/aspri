// Simple API test script for chat functionality
// This script can be run to verify that the API endpoints work correctly

console.log('Testing chat API endpoints...');

// Test configuration
const API_BASE_URL = 'http://localhost:8000';
const TEST_TOKEN = 'YOUR_JWT_TOKEN_HERE'; // Replace with a valid JWT token

// Test data
const testSession = {
  title: 'API Test Session'
};

const testMessage = {
  content: 'Hello, this is a test message',
  role: 'user',
  message_type: 'text'
};

// Function to make API requests
async function makeRequest(url, method, data = null) {
  const options = {
    method: method,
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${TEST_TOKEN}`
    }
  };
  
  if (data) {
    options.body = JSON.stringify(data);
  }
  
  try {
    const response = await fetch(`${API_BASE_URL}${url}`, options);
    const result = await response.json();
    console.log(`✓ ${method} ${url} - Status: ${response.status}`);
    return result;
  } catch (error) {
    console.error(`✗ ${method} ${url} - Error: ${error.message}`);
    return null;
  }
}

// Test functions
async function testCreateSession() {
  console.log('\n--- Testing Session Creation ---');
  const result = await makeRequest('/chat/sessions', 'POST', testSession);
  return result ? result.id : null;
}

async function testGetSessions() {
  console.log('\n--- Testing Session Retrieval ---');
  await makeRequest('/chat/sessions', 'GET');
}

async function testSendMessage(sessionId) {
  if (!sessionId) {
    console.log('\n--- Skipping Message Send (no session ID) ---');
    return;
  }
  
  console.log('\n--- Testing Message Send ---');
  await makeRequest(`/chat/sessions/${sessionId}/messages`, 'POST', testMessage);
}

async function testGetSession(sessionId) {
  if (!sessionId) {
    console.log('\n--- Skipping Session Get (no session ID) ---');
    return;
  }
  
  console.log('\n--- Testing Session Get ---');
  await makeRequest(`/chat/sessions/${sessionId}`, 'GET');
}

// Run tests
async function runTests() {
  console.log('Starting Chat API Tests...\n');
  
  // Test 1: Create session
  const sessionId = await testCreateSession();
  
  // Test 2: Get all sessions
  await testGetSessions();
  
  // Test 3: Send message
  await testSendMessage(sessionId);
  
  // Test 4: Get specific session
  await testGetSession(sessionId);
  
  console.log('\nChat API tests completed.');
}

// Run the tests
runTests();