// Simple manual test script for chat components
// This script can be run to verify that the components can be imported without errors

console.log('Testing chat components...');

try {
  // Try importing the ChatPage component
  const chatPage = require('../../pages/ChatPage');
  console.log('✓ ChatPage component imported successfully');
} catch (error) {
  console.error('✗ Error importing ChatPage component:', error.message);
}

try {
  // Try importing the UserDashboard component
  const userDashboard = require('../../pages/UserDashboard');
  console.log('✓ UserDashboard component imported successfully');
} catch (error) {
  console.error('✗ Error importing UserDashboard component:', error.message);
}

console.log('Chat component tests completed.');