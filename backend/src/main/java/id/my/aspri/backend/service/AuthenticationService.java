package id.my.aspri.backend.service;

import id.my.aspri.backend.api.dto.AuthResponse;
import id.my.aspri.backend.api.dto.LoginRequest;
import id.my.aspri.backend.api.dto.RegisterRequest;
import id.my.aspri.backend.core.security.JwtTokenProvider;
import id.my.aspri.backend.domain.UserProfile;
import id.my.aspri.backend.repo.UserProfileRepository;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.Optional;
import java.util.UUID;

/**
 * Service untuk handle authentication operations.
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class AuthenticationService {

    private final UserProfileRepository userProfileRepository;
    private final JwtTokenProvider jwtTokenProvider;
    private final BCryptPasswordEncoder passwordEncoder = new BCryptPasswordEncoder();

    /**
     * Register user baru.
     */
    @Transactional
    public AuthResponse register(RegisterRequest request) {
        log.info("Registering new user: {}", request.getEmail());
        
        // Check if email already exists
        Optional<UserProfile> existingUser = userProfileRepository.findByEmail(request.getEmail());
        if (existingUser.isPresent()) {
            throw new IllegalArgumentException("Email already registered");
        }
        
        // Generate UUID untuk user ID
        String userId = UUID.randomUUID().toString();
        
        // Hash password
        String passwordHash = passwordEncoder.encode(request.getPassword());
        
        // Create user profile
        UserProfile userProfile = UserProfile.builder()
            .userId(userId)
            .email(request.getEmail())
            .passwordHash(passwordHash)
            .preferredLanguage("id")
            .themePreference("light")
            .build();
        
        userProfileRepository.save(userProfile);
        
        // Generate tokens
        String accessToken = jwtTokenProvider.generateAccessToken(userId, request.getEmail());
        String refreshToken = jwtTokenProvider.generateRefreshToken(userId);
        
        log.info("User registered successfully: {} with ID: {}", request.getEmail(), userId);
        
        return new AuthResponse(
            accessToken,
            refreshToken,
            "Bearer",
            jwtTokenProvider.getExpirationTime(),
            new AuthResponse.UserInfo(userId, request.getEmail(), "user")
        );
    }

    /**
     * Login user.
     */
    @Transactional(readOnly = true)
    public AuthResponse login(LoginRequest request) {
        log.info("Authenticating user: {}", request.getEmail());
        
        // Find user by email
        UserProfile userProfile = userProfileRepository.findByEmail(request.getEmail())
            .orElseThrow(() -> new IllegalArgumentException("Invalid email or password"));
        
        // Verify password
        if (!passwordEncoder.matches(request.getPassword(), userProfile.getPasswordHash())) {
            throw new IllegalArgumentException("Invalid email or password");
        }
        
        // Generate tokens
        String accessToken = jwtTokenProvider.generateAccessToken(
            userProfile.getUserId(), 
            userProfile.getEmail()
        );
        String refreshToken = jwtTokenProvider.generateRefreshToken(userProfile.getUserId());
        
        log.info("User authenticated successfully: {}", request.getEmail());
        
        return new AuthResponse(
            accessToken,
            refreshToken,
            "Bearer",
            jwtTokenProvider.getExpirationTime(),
            new AuthResponse.UserInfo(
                userProfile.getUserId(), 
                userProfile.getEmail(), 
                "user"
            )
        );
    }

    /**
     * Logout user (untuk future implementation dengan token blacklist).
     */
    public void logout(String token) {
        log.info("User logged out");
        // TODO: Implement token blacklist jika diperlukan
        // Untuk JWT stateless, logout cukup di frontend dengan hapus token
    }

    /**
     * Refresh access token menggunakan refresh token.
     */
    @Transactional(readOnly = true)
    public AuthResponse refreshToken(String refreshToken) {
        log.info("Refreshing access token");
        
        if (!jwtTokenProvider.validateToken(refreshToken)) {
            throw new IllegalArgumentException("Invalid refresh token");
        }
        
        String userId = jwtTokenProvider.getUserIdFromToken(refreshToken);
        
        UserProfile userProfile = userProfileRepository.findById(userId)
            .orElseThrow(() -> new IllegalArgumentException("User not found"));
        
        String newAccessToken = jwtTokenProvider.generateAccessToken(
            userProfile.getUserId(), 
            userProfile.getEmail()
        );
        
        return new AuthResponse(
            newAccessToken,
            refreshToken,
            "Bearer",
            jwtTokenProvider.getExpirationTime(),
            new AuthResponse.UserInfo(
                userProfile.getUserId(), 
                userProfile.getEmail(), 
                "user"
            )
        );
    }
}
