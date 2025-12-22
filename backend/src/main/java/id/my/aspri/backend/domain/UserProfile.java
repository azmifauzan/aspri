package id.my.aspri.backend.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import org.hibernate.annotations.CreationTimestamp;
import org.hibernate.annotations.UpdateTimestamp;

import java.time.LocalDateTime;

@Data
@Entity
@Table(name = "user_profiles")
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class UserProfile {
    
    @Id
    private String userId; // UUID generated at registration
    
    @Column(nullable = false, unique = true)
    private String email;
    
    @Column(name = "password_hash", nullable = false)
    private String passwordHash;
    
    private String fullName;
    
    @Column(name = "aspri_name")
    private String aspriName;
    
    @Column(name = "aspri_persona", columnDefinition = "TEXT")
    private String aspriPersona;
    
    @Column(name = "call_preference")
    private String callPreference;
    
    @Column(name = "preferred_language", length = 5)
    private String preferredLanguage; // 'id' or 'en'
    
    @Column(name = "theme_preference", length = 10)
    private String themePreference; // 'light' or 'dark'
    
    @CreationTimestamp
    @Column(name = "created_at", nullable = false, updatable = false)
    private LocalDateTime createdAt;
    
    @UpdateTimestamp
    @Column(name = "updated_at")
    private LocalDateTime updatedAt;
}
