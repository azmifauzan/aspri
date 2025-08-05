// Simple functional test for chat component
// This script can be run in the browser console to test API connectivity

console.log('Testing chat functionality...');

// Test configuration
const API_BASE_URL = 'http://localhost:8888';
const TEST_TOKEN = localStorage.getItem('token'); // Get token from localStorage

// Function to test API connectivity
async function testChatAPI() {
  if (!TEST_TOKEN) {
    console.error('No authentication token found. Please log in first.');
    return;
  }

  console.log('Using token:', TEST_TOKEN.substring(0, 20) + '...');

  try {
    // Test 1: Get chat sessions
    console.log('\n--- Test 1: Get chat sessions ---');
    const sessionsResponse = await fetch(`${API_BASE_URL}/chat/sessions`, {
      headers: {
        'Authorization': `Bearer ${TEST_TOKEN}`,
        'Content-Type': 'application/json'
      }
    });
    
    console.log('Sessions response status:', sessionsResponse.status);
    if (sessionsResponse.ok) {
      const sessionsData = await sessionsResponse.json();
      console.log('✓ Successfully fetched chat sessions:', sessionsData);
    } else {
      const errorText = await sessionsResponse.text();
      console.error('✗ Failed to fetch chat sessions:', errorText);
    }

    // Test 2: Create a new session
    console.log('\n--- Test 2: Create new session ---');
    const createResponse = await fetch(`${API_BASE_URL}/chat/sessions`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${TEST_TOKEN}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        title: 'Test Session'
      })
    });
    
    console.log('Create session response status:', createResponse.status);
    if (createResponse.ok) {
      const sessionData = await createResponse.json();
      console.log('✓ Successfully created chat session:', sessionData);
      
      // Test 3: Send a test message
      console.log('\n--- Test 3: Send test message ---');
      const messageResponse = await fetch(`${API_BASE_URL}/chat/sessions/${sessionData.id}/messages`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${TEST_TOKEN}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          content: 'Hello, this is a test message',
          role: 'user',
          message_type: 'text'
        })
      });
      
      console.log('Message response status:', messageResponse.status);
      if (messageResponse.ok) {
        const messageData = await messageResponse.json();
        console.log('✓ Successfully sent message:', messageData);
      } else {
        const errorText = await messageResponse.text();
        console.error('✗ Failed to send message:', errorText);
      }
    } else {
      const errorText = await createResponse.text();
      console.error('✗ Failed to create chat session:', errorText);
    }

    console.log('\n--- Test completed ---');
  } catch (error) {
    console.error('Test failed with error:', error);
  }
}

// Run the test
testChatAPI();