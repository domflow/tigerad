#!/bin/bash

# API Testing Script for Notification System
# This script tests all API endpoints to ensure they are working correctly

set -e

echo "==================================="
echo "Notification System API Test Suite"
echo "==================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
API_BASE_URL=${API_BASE_URL:-"http://localhost/api.php"}
USERNAME="testuser$(date +%s)"
EMAIL="test$(date +%s)@example.com"
PASSWORD="testpass123"

# Test results
TESTS_PASSED=0
TESTS_FAILED=0

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
    ((TESTS_PASSED++))
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
    ((TESTS_FAILED++))
}

print_info() {
    echo -e "${YELLOW}[INFO]${NC} $1"
}

test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local expected_status=$4
    local description=$5
    
    print_info "Testing: $description"
    
    if [ "$method" == "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" -H "Authorization: Bearer $TOKEN" "$API_BASE_URL$endpoint")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $TOKEN" \
            -d "$data" \
            "$API_BASE_URL$endpoint")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [ "$http_code" == "$expected_status" ]; then
        print_success "$description (HTTP $http_code)"
        echo "$body" | jq . 2>/dev/null || echo "$body"
        return 0
    else
        print_error "$description (Expected: $expected_status, Got: $http_code)"
        echo "$body" | jq . 2>/dev/null || echo "$body"
        return 1
    fi
}

# Test 1: Register new user
print_info "Test 1: Registering new user"
register_response=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -d "{&quot;username&quot;:&quot;$USERNAME&quot;,&quot;email&quot;:&quot;$EMAIL&quot;,&quot;password&quot;:&quot;$PASSWORD&quot;}" \
    "$API_BASE_URL/register")

if echo "$register_response" | grep -q "User created successfully"; then
    print_success "User registration"
    TOKEN=$(echo "$register_response" | jq -r '.token' 2>/dev/null)
    USER_ID=$(echo "$register_response" | jq -r '.user_id' 2>/dev/null)
else
    print_error "User registration"
    echo "$register_response" | jq . 2>/dev/null || echo "$register_response"
    exit 1
fi

# Test 2: Login
print_info "Test 2: User login"
login_response=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -d "{&quot;username&quot;:&quot;$USERNAME&quot;,&quot;password&quot;:&quot;$PASSWORD&quot;}" \
    "$API_BASE_URL/login")

if echo "$login_response" | grep -q "Login successful"; then
    print_success "User login"
else
    print_error "User login"
    echo "$login_response" | jq . 2>/dev/null || echo "$login_response"
fi

# Test 3: Get current user
test_endpoint "GET" "/users" "" "200" "Get current user info"

# Test 4: Get notification types
test_endpoint "GET" "/notification-types" "" "200" "Get notification types"

# Test 5: Create notification
test_endpoint "POST" "/notifications" \
    '{"title":"Test Notification","message":"This is a test notification"}' \
    "201" "Create notification"

# Get notification ID from response
NOTIFICATION_ID=1

# Test 6: Get all notifications
test_endpoint "GET" "/notifications" "" "200" "Get all notifications"

# Test 7: Get specific notification
test_endpoint "GET" "/notifications/$NOTIFICATION_ID" "" "200" "Get specific notification"

# Test 8: Mark notification as read
test_endpoint "PUT" "/notifications/$NOTIFICATION_ID" "" "200" "Mark notification as read"

# Test 9: Create another notification
test_endpoint "POST" "/notifications" \
    '{"title":"Second Test","message":"Another test notification"}' \
    "201" "Create second notification"

# Test 10: Get unread notifications only
test_endpoint "GET" "/notifications?unread=true" "" "200" "Get unread notifications"

# Test 11: Delete notification
test_endpoint "DELETE" "/notifications/$NOTIFICATION_ID" "" "200" "Delete notification"

# Test 12: Update user info
test_endpoint "PUT" "/users" \
    '{"email":"updated@example.com"}' \
    "200" "Update user information"

# Test 13: Test with invalid token
print_info "Test 13: Testing with invalid token"
invalid_response=$(curl -s -w "\n%{http_code}" \
    -H "Authorization: Bearer invalid_token" \
    "$API_BASE_URL/notifications")
invalid_http_code=$(echo "$invalid_response" | tail -n1)

if [ "$invalid_http_code" == "401" ]; then
    print_success "Invalid token handling"
else
    print_error "Invalid token handling (Expected: 401, Got: $invalid_http_code)"
fi

# Test 14: Test without token
print_info "Test 14: Testing without token"
no_token_response=$(curl -s -w "\n%{http_code}" \
    "$API_BASE_URL/notifications")
no_token_http_code=$(echo "$no_token_response" | tail -n1)

if [ "$no_token_http_code" == "401" ]; then
    print_success "Missing token handling"
else
    print_error "Missing token handling (Expected: 401, Got: $no_token_http_code)"
fi

# Test 15: Queue processing
print_info "Test 15: Testing queue processor"
queue_response=$(php /var/www/html/queue_processor.php 2>/dev/null)
if echo "$queue_response" | grep -q "processed"; then
    print_success "Queue processor"
    echo "$queue_response" | jq . 2>/dev/null || echo "$queue_response"
else
    print_error "Queue processor"
fi

# Summary
echo
echo "==================================="
echo "Test Summary"
echo "==================================="
echo "Tests Passed: $TESTS_PASSED"
echo "Tests Failed: $TESTS_FAILED"
echo "Total Tests: $((TESTS_PASSED + TESTS_FAILED))"

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed!${NC}"
    exit 1
fi