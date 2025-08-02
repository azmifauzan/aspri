// src/pages/RegistrationPage.tsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { Link } from 'react-router-dom';
import axios from 'axios';

interface RegistrationFormData {
  name: string;
  birth_date: number | '';
  birth_month: number | '';
  call_preference: string;
  aspri_name: string;
  aspri_persona: string;
}

interface PersonaState {
  selectedOption: string;
  customValue: string;
}

export default function RegistrationPage() {
  const navigate = useNavigate();
  const { user, token, updateUser } = useAuth();
  const { t } = useTranslation();
  
  const [formData, setFormData] = useState<RegistrationFormData>({
    name: '',
    birth_date: '',
    birth_month: '',
    call_preference: '',
    aspri_name: '',
    aspri_persona: ''
  });
  
  const [personaState, setPersonaState] = useState<PersonaState>({
    selectedOption: '',
    customValue: ''
  });
  
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  // Redirect if user is not logged in or already registered
  if (!user || user.is_registered) {
    navigate('/login');
  }
  
  const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
  
  // Predefined persona options
  const personaOptions = [
    { value: 'asisten_pria', label: t('register.aspri_persona_options.asisten_pria') },
    { value: 'wanita', label: t('register.aspri_persona_options.wanita') },
    { value: 'kucing', label: t('register.aspri_persona_options.kucing') },
    { value: 'anjing', label: t('register.aspri_persona_options.anjing') },
    { value: 'custom', label: t('register.aspri_persona_options.custom') }
  ];
  
  const validateForm = () => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.name.trim()) {
      newErrors.name = t('register.errors.name_required');
    }
    
    if (formData.birth_date === '') {
      newErrors.birth_date = t('register.errors.birth_date_required');
    } else if (formData.birth_date < 1 || formData.birth_date > 31) {
      newErrors.birth_date = t('register.errors.birth_date_invalid');
    }
    
    if (formData.birth_month === '') {
      newErrors.birth_month = t('register.errors.birth_month_required');
    } else if (formData.birth_month < 1 || formData.birth_month > 12) {
      newErrors.birth_month = t('register.errors.birth_month_invalid');
    }
    
    if (!formData.call_preference.trim()) {
      newErrors.call_preference = t('register.errors.call_preference_required');
    }
    
    if (!formData.aspri_name.trim()) {
      newErrors.aspri_name = t('register.errors.aspri_name_required');
    }
    
    if (!formData.aspri_persona.trim()) {
      newErrors.aspri_persona = t('register.errors.aspri_persona_required');
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };
  
  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: name === 'birth_date' || name === 'birth_month' ? (value === '' ? '' : parseInt(value)) : value
    }));
    
    // Clear error when user starts typing
    if (errors[name]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[name];
        return newErrors;
      });
    }
  };
  
  const handlePersonaChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const selectedValue = e.target.value;
    setPersonaState(prev => ({
      ...prev,
      selectedOption: selectedValue
    }));
    
    // Update formData based on selection
    if (selectedValue === 'custom') {
      setFormData(prev => ({
        ...prev,
        aspri_persona: personaState.customValue
      }));
    } else if (selectedValue !== '') {
      // Find the label for the selected option
      const selectedOption = personaOptions.find(option => option.value === selectedValue);
      setFormData(prev => ({
        ...prev,
        aspri_persona: selectedOption ? selectedOption.label : ''
      }));
    } else {
      setFormData(prev => ({
        ...prev,
        aspri_persona: ''
      }));
    }
    
    // Clear error when user makes selection
    if (errors.aspri_persona) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors.aspri_persona;
        return newErrors;
      });
    }
  };
  
  const handleCustomPersonaChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const customValue = e.target.value;
    setPersonaState(prev => ({
      ...prev,
      customValue
    }));
    
    setFormData(prev => ({
      ...prev,
      aspri_persona: customValue
    }));
    
    // Clear error when user starts typing
    if (errors.aspri_persona) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors.aspri_persona;
        return newErrors;
      });
    }
  };
  
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) return;
    
    setIsSubmitting(true);
    
    try {
      const response = await axios.post(
        `${API_BASE_URL}/auth/register`,
        {
          name: formData.name,
          birth_date: formData.birth_date,
          birth_month: formData.birth_month,
          call_preference: formData.call_preference,
          aspri_name: formData.aspri_name,
          aspri_persona: formData.aspri_persona
        },
        {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        }
      );
      
      // Registration successful, update user context and redirect to dashboard
      if (response.data) {
        // Update the user state with the new data from registration
        updateUser(response.data);
        navigate('/dashboard');
      }
    } catch (error: any) {
      console.error('Registration failed:', error);
      alert(t('register.errors.registration_failed') + (error.response?.data?.detail || ''));
    } finally {
      setIsSubmitting(false);
    }
  };
  
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-zinc-900 dark:via-zinc-800 dark:to-zinc-900 flex items-center justify-center px-4 py-8">
      <div className="max-w-2xl w-full">
        {/* Back button */}
        <Link 
          to="/" 
          className="inline-flex items-center gap-2 text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition mb-6"
        >
          <ArrowLeft size={20} />
          {t('common.back_to_home')}
        </Link>
        
        {/* Registration Card */}
        <div className="bg-white dark:bg-zinc-800 rounded-2xl shadow-xl p-6 md:p-8">
          <div className="text-center mb-8">
            <h1 className="text-3xl font-bold text-zinc-900 dark:text-white mb-2">
              {t('register.complete_profile')}
            </h1>
            <p className="text-zinc-600 dark:text-zinc-400">
              {t('register.complete_profile_description')}
            </p>
          </div>
          
          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Name */}
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                {t('register.name')}
              </label>
              <input
                type="text"
                id="name"
                name="name"
                value={formData.name}
                onChange={handleChange}
                className={`w-full px-4 py-2 rounded-lg border ${
                  errors.name ? 'border-red-500' : 'border-zinc-300 dark:border-zinc-600'
                } bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white focus:ring-2 focus:ring-brand focus:border-transparent`}
                placeholder={t('register.name_placeholder')}
              />
              {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
            </div>
            
            {/* Birth Date and Month */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label htmlFor="birth_date" className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                  {t('register.birth_date')}
                </label>
                <select
                  id="birth_date"
                  name="birth_date"
                  value={formData.birth_date}
                  onChange={handleChange}
                  className={`w-full px-4 py-2 rounded-lg border ${
                    errors.birth_date ? 'border-red-500' : 'border-zinc-300 dark:border-zinc-600'
                  } bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white focus:ring-2 focus:ring-brand focus:border-transparent`}
                >
                  <option value="">{t('register.select_day')}</option>
                  {Array.from({ length: 31 }, (_, i) => i + 1).map(day => (
                    <option key={day} value={day}>{day}</option>
                  ))}
                </select>
                {errors.birth_date && <p className="mt-1 text-sm text-red-500">{errors.birth_date}</p>}
              </div>
              
              <div>
                <label htmlFor="birth_month" className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                  {t('register.birth_month')}
                </label>
                <select
                  id="birth_month"
                  name="birth_month"
                  value={formData.birth_month}
                  onChange={handleChange}
                  className={`w-full px-4 py-2 rounded-lg border ${
                    errors.birth_month ? 'border-red-500' : 'border-zinc-300 dark:border-zinc-600'
                  } bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white focus:ring-2 focus:ring-brand focus:border-transparent`}
                >
                  <option value="">{t('register.select_month')}</option>
                  {[
                    { value: 1, label: t('months.january') },
                    { value: 2, label: t('months.february') },
                    { value: 3, label: t('months.march') },
                    { value: 4, label: t('months.april') },
                    { value: 5, label: t('months.may') },
                    { value: 6, label: t('months.june') },
                    { value: 7, label: t('months.july') },
                    { value: 8, label: t('months.august') },
                    { value: 9, label: t('months.september') },
                    { value: 10, label: t('months.october') },
                    { value: 11, label: t('months.november') },
                    { value: 12, label: t('months.december') }
                  ].map(month => (
                    <option key={month.value} value={month.value}>{month.label}</option>
                  ))}
                </select>
                {errors.birth_month && <p className="mt-1 text-sm text-red-500">{errors.birth_month}</p>}
              </div>
            </div>
            
            {/* Call Preference */}
            <div>
              <label htmlFor="call_preference" className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                {t('register.call_preference')}
              </label>
              <input
                type="text"
                id="call_preference"
                name="call_preference"
                value={formData.call_preference}
                onChange={handleChange}
                className={`w-full px-4 py-2 rounded-lg border ${
                  errors.call_preference ? 'border-red-500' : 'border-zinc-300 dark:border-zinc-600'
                } bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white focus:ring-2 focus:ring-brand focus:border-transparent`}
                placeholder={t('register.call_preference_placeholder')}
              />
              {errors.call_preference && <p className="mt-1 text-sm text-red-500">{errors.call_preference}</p>}
            </div>
            
            {/* ASPRI Name */}
            <div>
              <label htmlFor="aspri_name" className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                {t('register.aspri_name')}
              </label>
              <input
                type="text"
                id="aspri_name"
                name="aspri_name"
                value={formData.aspri_name}
                onChange={handleChange}
                className={`w-full px-4 py-2 rounded-lg border ${
                  errors.aspri_name ? 'border-red-500' : 'border-zinc-300 dark:border-zinc-600'
                } bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white focus:ring-2 focus:ring-brand focus:border-transparent`}
                placeholder={t('register.aspri_name_placeholder')}
              />
              {errors.aspri_name && <p className="mt-1 text-sm text-red-500">{errors.aspri_name}</p>}
            </div>
            
            {/* ASPRI Persona - Updated with Dropdown */}
            <div>
              <label htmlFor="aspri_persona" className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                {t('register.aspri_persona')}
              </label>
              <select
                id="aspri_persona"
                value={personaState.selectedOption}
                onChange={handlePersonaChange}
                className={`w-full px-4 py-2 rounded-lg border ${
                  errors.aspri_persona ? 'border-red-500' : 'border-zinc-300 dark:border-zinc-600'
                } bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white focus:ring-2 focus:ring-brand focus:border-transparent`}
              >
                <option value="">{t('register.aspri_persona_placeholder')}</option>
                {personaOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              
              {/* Custom Input Field - Shows when "custom" is selected */}
              {personaState.selectedOption === 'custom' && (
                <div className="mt-2">
                  <input
                    type="text"
                    value={personaState.customValue}
                    onChange={handleCustomPersonaChange}
                    className={`w-full px-4 py-2 rounded-lg border ${
                      errors.aspri_persona ? 'border-red-500' : 'border-zinc-300 dark:border-zinc-600'
                    } bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white focus:ring-2 focus:ring-brand focus:border-transparent`}
                    placeholder={t('register.custom_persona_placeholder')}
                  />
                </div>
              )}
              
              {errors.aspri_persona && <p className="mt-1 text-sm text-red-500">{errors.aspri_persona}</p>}
            </div>
            
            {/* Submit Button */}
            <div className="pt-4">
              <button
                type="submit"
                disabled={isSubmitting}
                className="w-full py-3 px-4 bg-brand text-white rounded-lg hover:bg-brand/90 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isSubmitting ? (
                  <div className="flex items-center justify-center">
                    <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                    {t('register.submitting')}
                  </div>
                ) : (
                  t('register.complete_registration')
                )}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}