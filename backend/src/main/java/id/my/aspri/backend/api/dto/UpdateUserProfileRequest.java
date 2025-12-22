package id.my.aspri.backend.api.dto;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Pattern;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class UpdateUserProfileRequest {
    private String fullName;
    private String aspriName;
    private String aspriPersona;
    private String callPreference;
    
    @Pattern(regexp = "^(id|en)$", message = "Language must be 'id' or 'en'")
    private String preferredLanguage;
    
    @Pattern(regexp = "^(light|dark)$", message = "Theme must be 'light' or 'dark'")
    private String themePreference;
}
