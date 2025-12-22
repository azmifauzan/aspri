package id.my.aspri.backend.api.dto;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.time.LocalDateTime;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class UserProfileResponse {
    private String userId;
    private String email;
    private String fullName;
    private String aspriName;
    private String aspriPersona;
    private String callPreference;
    private String preferredLanguage;
    private String themePreference;
    private LocalDateTime createdAt;
    private LocalDateTime updatedAt;
}
