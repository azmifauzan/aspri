package id.my.aspri.backend.service;

import id.my.aspri.backend.domain.UserProfile;
import id.my.aspri.backend.repo.UserProfileRepository;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.Optional;

@Slf4j
@Service
@RequiredArgsConstructor
public class UserProfileService {
    
    private final UserProfileRepository userProfileRepository;
    
    @Transactional(readOnly = true)
    public Optional<UserProfile> getUserProfile(String userId) {
        return userProfileRepository.findById(userId);
    }
    
    @Transactional
    public UserProfile createUserProfile(String userId, String email) {
        // Check if profile already exists
        Optional<UserProfile> existing = userProfileRepository.findById(userId);
        if (existing.isPresent()) {
            return existing.get();
        }
        
        // Create new profile with defaults
        UserProfile profile = UserProfile.builder()
            .userId(userId)
            .email(email)
            .aspriName("ASPRI")
            .aspriPersona("Saya adalah asisten pribadi yang membantu Anda mengelola jadwal, catatan, dan keuangan.")
            .callPreference("Anda")
            .preferredLanguage("id")
            .themePreference("light")
            .build();
            
        log.info("Creating new user profile for userId: {}", userId);
        return userProfileRepository.save(profile);
    }
    
    @Transactional
    public UserProfile updateUserProfile(String userId, UserProfile updates) {
        UserProfile profile = userProfileRepository.findById(userId)
            .orElseThrow(() -> new RuntimeException("User profile not found"));
        
        if (updates.getFullName() != null) {
            profile.setFullName(updates.getFullName());
        }
        if (updates.getAspriName() != null) {
            profile.setAspriName(updates.getAspriName());
        }
        if (updates.getAspriPersona() != null) {
            profile.setAspriPersona(updates.getAspriPersona());
        }
        if (updates.getCallPreference() != null) {
            profile.setCallPreference(updates.getCallPreference());
        }
        if (updates.getPreferredLanguage() != null) {
            profile.setPreferredLanguage(updates.getPreferredLanguage());
        }
        if (updates.getThemePreference() != null) {
            profile.setThemePreference(updates.getThemePreference());
        }
        
        log.info("Updating user profile for userId: {}", userId);
        return userProfileRepository.save(profile);
    }
}
