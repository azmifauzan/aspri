# Authentication System - ASPRI

## Overview

ASPRI menggunakan **manual authentication system** yang fully portable dan tidak bergantung pada provider eksternal seperti Supabase Auth atau OAuth providers. Sistem ini menggunakan:

- **BCrypt** untuk password hashing
- **JWT (JSON Web Tokens)** untuk session management
- **Spring Security** untuk authorization
- **PostgreSQL** untuk user data storage

## Architecture

```
┌─────────────┐          ┌──────────────┐          ┌──────────────┐
│   Frontend  │          │   Backend    │          │  PostgreSQL  │
│  (Angular)  │          │ (Spring Boot)│          │              │
└──────┬──────┘          └──────┬───────┘          └──────┬───────┘
       │                        │                         │
       │  POST /auth/register   │                         │
       │──────────────────────> │                         │
       │                        │  Hash password (BCrypt) │
       │                        │──────────────────────>  │
       │                        │  Save user_profile      │
       │                        │──────────────────────>  │
       │                        │  Generate JWT           │
       │ <──────────────────────│                         │
       │   {token, user}        │                         │
       │                        │                         │
       │  Store token           │                         │
       │  (localStorage)        │                         │
       │                        │                         │
       │  GET /api/resource     │                         │
       │  Authorization: Bearer │                         │
       │──────────────────────> │                         │
       │                        │  Validate JWT           │
       │                        │  Extract user_id        │
       │                        │  Query data by user_id  │
       │                        │──────────────────────>  │
       │                        │ <────────────────────── │
       │ <──────────────────────│                         │
       │   {data}               │                         │
```

## Components

### 1. User Profile Entity

**Location**: [backend/src/main/java/id/my/aspri/backend/domain/UserProfile.java](../backend/src/main/java/id/my/aspri/backend/domain/UserProfile.java)

```java
@Entity
@Table(name = "user_profiles")
public class UserProfile {
    @Id
    @Column(name = "user_id")
    private String userId;  // UUID as String
    
    @Column(nullable = false, unique = true)
    private String email;
    
    @Column(name = "password_hash", nullable = false)
    private String passwordHash;  // BCrypt hash
    
    @Column(name = "full_name")
    private String fullName;
    
    // Persona & preferences
    @Column(name = "aspri_name")
    private String aspriName = "ASPRI";
    
    @Column(name = "aspri_persona")
    private String aspriPersona;
    
    @Column(name = "call_preference")
    private String callPreference;
    
    @Column(name = "preferred_language")
    private String preferredLanguage = "id";
    
    @Column(name = "theme_preference")
    private String themePreference = "light";
    
    @Column(name = "created_at")
    private LocalDateTime createdAt;
    
    @Column(name = "updated_at")
    private LocalDateTime updatedAt;
}
```

### 2. JWT Token Provider

**Location**: [backend/src/main/java/id/my/aspri/backend/core/security/JwtTokenProvider.java](../backend/src/main/java/id/my/aspri/backend/core/security/JwtTokenProvider.java)

**Key Functions**:
- `generateToken(userId, email)` - Generate access token
- `generateRefreshToken(userId)` - Generate refresh token
- `validateToken(token)` - Validate token signature and expiration
- `getUserIdFromToken(token)` - Extract user_id from token claims
- `getEmailFromToken(token)` - Extract email from token claims

**Configuration**:
```yaml
jwt:
  secret: ${JWT_SECRET}  # Minimum 32 characters
  expiration: 86400000   # 24 hours (in milliseconds)
  refresh-expiration: 604800000  # 7 days
```

**Token Structure**:
```json
{
  "sub": "user-id-uuid",
  "email": "user@example.com",
  "iat": 1640000000,
  "exp": 1640086400
}
```

### 3. JWT Authentication Filter

**Location**: [backend/src/main/java/id/my/aspri/backend/core/security/JwtAuthenticationFilter.java](../backend/src/main/java/id/my/aspri/backend/core/security/JwtAuthenticationFilter.java)

**Flow**:
1. Intercept incoming HTTP requests
2. Extract JWT token from `Authorization: Bearer <token>` header
3. Validate token using `JwtTokenProvider`
4. Extract user_id from token
5. Set authentication in Spring SecurityContext
6. Pass request to controller

### 4. Security Configuration

**Location**: [backend/src/main/java/id/my/aspri/backend/config/SecurityConfig.java](../backend/src/main/java/id/my/aspri/backend/config/SecurityConfig.java)

**Security Rules**:
- Public endpoints: `/api/auth/register`, `/api/auth/login`, `/api/health`
- All other endpoints require authentication
- CSRF disabled (stateless JWT)
- CORS configured for frontend origin
- Session management: STATELESS

### 5. Authentication Service

**Location**: [backend/src/main/java/id/my/aspri/backend/service/AuthenticationService.java](../backend/src/main/java/id/my/aspri/backend/service/AuthenticationService.java)

**Key Methods**: `register()`, `login()`, `refreshToken()`

### 6. Frontend Authentication Service

**Location**: [frontend/src/app/services/auth.service.ts](../frontend/src/app/services/auth.service.ts)

Handles registration, login, logout, and token storage.

## API Endpoints

### Public Endpoints (No Authentication)

#### POST /api/auth/register
Register new user account.

**Request**:
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "fullName": "John Doe"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "refreshToken": "eyJhbGciOiJIUzI1NiIs...",
    "user": {
      "userId": "550e8400-e29b-41d4-a716-446655440000",
      "email": "user@example.com",
      "fullName": "John Doe",
      "aspriName": "ASPRI",
      "preferredLanguage": "id"
    }
  }
}
```

#### POST /api/auth/login
Login with email and password.

**Request**:
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!"
}
```

**Response**: Same as register

#### POST /api/auth/refresh
Refresh access token using refresh token.

**Request**:
```json
{
  "refreshToken": "eyJhbGciOiJIUzI1NiIs..."
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "refreshToken": "eyJhbGciOiJIUzI1NiIs..."
  }
}
```

### Protected Endpoints (Require Authentication)

All other endpoints require `Authorization: Bearer <token>` header.

#### GET /api/auth/profile
Get current user profile.

**Response**:
```json
{
  "success": true,
  "data": {
    "userId": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "fullName": "John Doe",
    "aspriName": "ASPRI",
    "aspriPersona": "Friendly and helpful",
    "callPreference": "Kak",
    "preferredLanguage": "id",
    "themePreference": "dark"
  }
}
```

#### PUT /api/auth/profile
Update user profile.

**Request**:
```json
{
  "fullName": "John Smith",
  "aspriName": "Asisten Saya",
  "callPreference": "Bapak",
  "preferredLanguage": "en",
  "themePreference": "light"
}
```

## Security Considerations

### Password Requirements

**Current** (can be strengthened):
- Minimum 6 characters
- No complexity requirements yet

**Recommended for Production**:
- Minimum 8 characters
- At least 1 uppercase letter
- At least 1 lowercase letter
- At least 1 number
- At least 1 special character

### JWT Secret Key

**Requirements**:
- Minimum 32 characters
- Random and unpredictable
- Never commit to version control
- Store in environment variables
- Rotate periodically in production

**Generate secure key**:
```bash
# Using openssl
openssl rand -base64 48

# Using Node.js
node -e "console.log(require('crypto').randomBytes(48).toString('base64'))"
```

### Token Expiration

**Current Configuration**:
- Access token: 24 hours
- Refresh token: 7 days

**Best Practices**:
- Access token: 15 minutes to 1 hour (short-lived)
- Refresh token: 7 to 30 days
- Implement token refresh flow
- Implement token blacklist for logout

### Rate Limiting

Add rate limiting for auth endpoints to prevent brute force attacks.

### HTTPS Only

In production, always use HTTPS for secure token transmission.

## Testing

### Manual Testing

```bash
# Register
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"pass123","fullName":"Test User"}'

# Login
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"pass123"}'

# Get Profile (with token)
curl -X GET http://localhost:8080/api/auth/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Unit Tests

Test password hashing, JWT generation/validation, and authentication flows.

## Troubleshooting

### "Invalid JWT signature"
- Check JWT_SECRET consistency
- Verify token format
- Check for whitespace

### "Token expired"
- Implement token refresh flow
- Check system time
- Adjust token expiration

### "User not found"
- Check email case sensitivity
- Verify database connection
- Check user_profiles table

### "Invalid credentials"
- Verify password correct
- Check BCrypt configuration
- Check database query

## References

- [Spring Security Documentation](https://docs.spring.io/spring-security/reference/)
- [JWT.io](https://jwt.io/) - JWT debugger
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
