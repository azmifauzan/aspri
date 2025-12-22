package id.my.aspri.backend.api;

import id.my.aspri.backend.api.dto.*;
import id.my.aspri.backend.domain.UserProfile;
import id.my.aspri.backend.service.AuthenticationService;
import id.my.aspri.backend.service.UserProfileService;
import jakarta.validation.Valid;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.web.bind.annotation.*;

import java.util.Map;

@Slf4j
@RestController
@RequestMapping("/api/auth")
@RequiredArgsConstructor
public class AuthController {
    
    private final AuthenticationService authenticationService;
    private final UserProfileService userProfileService;
    
    /**
     * Register user baru.
     */
    @PostMapping("/register")
    public ResponseEntity<ApiResponse<AuthResponse>> register(
            @Valid @RequestBody RegisterRequest request) {
        
        try {
            AuthResponse response = authenticationService.register(request);
            return ResponseEntity.ok(ApiResponse.success("Registration successful", response));
        } catch (IllegalArgumentException e) {
            return ResponseEntity.badRequest()
                .body(ApiResponse.error(e.getMessage()));
        }
    }
    
    /**
     * Login user.
     */
    @PostMapping("/login")
    public ResponseEntity<ApiResponse<AuthResponse>> login(
            @Valid @RequestBody LoginRequest request) {
        
        try {
            AuthResponse response = authenticationService.login(request);
            return ResponseEntity.ok(ApiResponse.success("Login successful", response));
        } catch (IllegalArgumentException e) {
            return ResponseEntity.badRequest()
                .body(ApiResponse.error(e.getMessage()));
        }
    }
    
    /**
     * Logout user.
     */
    @PostMapping("/logout")
    public ResponseEntity<ApiResponse<Void>> logout(
            @RequestHeader("Authorization") String authHeader) {
        
        String token = authHeader.substring(7); // Remove "Bearer "
        authenticationService.logout(token);
        
        return ResponseEntity.ok(ApiResponse.success("Logout successful", null));
    }
    
    /**
     * Refresh access token.
     */
    @PostMapping("/refresh")
    public ResponseEntity<ApiResponse<AuthResponse>> refreshToken(
            @RequestBody Map<String, String> request) {
        
        try {
            String refreshToken = request.get("refreshToken");
            AuthResponse response = authenticationService.refreshToken(refreshToken);
            return ResponseEntity.ok(ApiResponse.success("Token refreshed", response));
        } catch (IllegalArgumentException e) {
            return ResponseEntity.badRequest()
                .body(ApiResponse.error(e.getMessage()));
        }
    }
    
    /**
     * Get current user profile.
     */
    @GetMapping("/profile")
    public ResponseEntity<ApiResponse<UserProfileResponse>> getProfile() {
        
        String userId = getCurrentUserId();
        
        UserProfile profile = userProfileService.getUserProfile(userId)
            .orElseThrow(() -> new IllegalArgumentException("User not found"));
        
        UserProfileResponse response = mapToResponse(profile);
        return ResponseEntity.ok(ApiResponse.success(response));
    }
    
    /**
     * Update user profile.
     */
    @PutMapping("/profile")
    public ResponseEntity<ApiResponse<UserProfileResponse>> updateProfile(
            @Valid @RequestBody UpdateUserProfileRequest request) {
        
        String userId = getCurrentUserId();
        
        UserProfile updates = UserProfile.builder()
            .fullName(request.getFullName())
            .aspriName(request.getAspriName())
            .aspriPersona(request.getAspriPersona())
            .callPreference(request.getCallPreference())
            .preferredLanguage(request.getPreferredLanguage())
            .themePreference(request.getThemePreference())
            .build();
        
        UserProfile updated = userProfileService.updateUserProfile(userId, updates);
        UserProfileResponse response = mapToResponse(updated);
        
        return ResponseEntity.ok(ApiResponse.success("Profile updated successfully", response));
    }
    
    /**
     * Get current authenticated user info.
     */
    @GetMapping("/me")
    public ResponseEntity<ApiResponse<UserProfileResponse>> getCurrentUser() {
        
        String userId = getCurrentUserId();
        
        log.info("Getting current user info for userId: {}", userId);
        
        UserProfile profile = userProfileService.getUserProfile(userId)
            .orElseThrow(() -> new IllegalArgumentException("User not found"));
        
        UserProfileResponse response = mapToResponse(profile);
        return ResponseEntity.ok(ApiResponse.success(response));
    }
    
    /**
     * Get current user ID dari security context.
     */
    private String getCurrentUserId() {
        Authentication authentication = SecurityContextHolder.getContext().getAuthentication();
        if (authentication == null || !authentication.isAuthenticated()) {
            throw new IllegalStateException("User not authenticated");
        }
        return authentication.getName(); // user ID set as principal
    }
    
    private UserProfileResponse mapToResponse(UserProfile profile) {
        return UserProfileResponse.builder()
            .userId(profile.getUserId())
            .email(profile.getEmail())
            .fullName(profile.getFullName())
            .aspriName(profile.getAspriName())
            .aspriPersona(profile.getAspriPersona())
            .callPreference(profile.getCallPreference())
            .preferredLanguage(profile.getPreferredLanguage())
            .themePreference(profile.getThemePreference())
            .createdAt(profile.getCreatedAt())
            .updatedAt(profile.getUpdatedAt())
            .build();
    }
}
