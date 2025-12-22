// src/pages/ProfilePage.tsx
import { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import axios from 'axios';

interface ProfileFormData {
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

export default function ProfilePage() {
  const { user, token, updateUser } = useAuth();
  const { t } = useTranslation();

  const [formData, setFormData] = useState<ProfileFormData>({
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
  const [isSuccess, setIsSuccess] = useState(false);

  const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

  // Predefined persona options
  const personaOptions = [
    { value: 'pria', label: t('register.aspri_persona_options.pria') },
    { value: 'wanita', label: t('register.aspri_persona_options.wanita') },
    { value: 'kucing', label: t('register.aspri_persona_options.kucing') },
    { value: 'anjing', label: t('register.aspri_persona_options.anjing') },
    { value: 'custom', label: t('register.aspri_persona_options.custom') }
  ];

  useEffect(() => {
    if (user) {
      setFormData({
        name: user.name || '',
        birth_date: user.birth_date || '',
        birth_month: user.birth_month || '',
        call_preference: user.call_preference || '',
        aspri_name: user.aspri_name || '',
        aspri_persona: user.aspri_persona || ''
      });

      const persona = user.aspri_persona || '';
      const isCustom = !personaOptions.some(p => p.label === persona);

      if (isCustom) {
        setPersonaState({ selectedOption: 'custom', customValue: persona });
      } else {
        const option = personaOptions.find(p => p.label === persona);
        setPersonaState({ selectedOption: option ? option.value : '', customValue: '' });
      }
    }
  }, [user]);

  const validateForm = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.name.trim()) newErrors.name = t('register.errors.name_required');
    if (formData.birth_date === '') newErrors.birth_date = t('register.errors.birth_date_required');
    if (formData.birth_month === '') newErrors.birth_month = t('register.errors.birth_month_required');
    if (!formData.call_preference.trim()) newErrors.call_preference = t('register.errors.call_preference_required');
    if (!formData.aspri_name.trim()) newErrors.aspri_name = t('register.errors.aspri_name_required');
    if (!formData.aspri_persona.trim()) newErrors.aspri_persona = t('register.errors.aspri_persona_required');

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: name === 'birth_date' || name === 'birth_month' ? (value === '' ? '' : parseInt(value)) : value
    }));
    if (errors[name]) setErrors(prev => ({ ...prev, [name]: '' }));
  };

  const handlePersonaChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const selectedValue = e.target.value;
    setPersonaState(prev => ({ ...prev, selectedOption: selectedValue }));

    if (selectedValue === 'custom') {
      setFormData(prev => ({ ...prev, aspri_persona: personaState.customValue }));
    } else if (selectedValue !== '') {
      const selectedOption = personaOptions.find(option => option.value === selectedValue);
      setFormData(prev => ({ ...prev, aspri_persona: selectedOption ? selectedOption.label : '' }));
    } else {
      setFormData(prev => ({ ...prev, aspri_persona: '' }));
    }
    if (errors.aspri_persona) setErrors(prev => ({ ...prev, aspri_persona: '' }));
  };

  const handleCustomPersonaChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const customValue = e.target.value;
    setPersonaState(prev => ({ ...prev, customValue }));
    setFormData(prev => ({ ...prev, aspri_persona: customValue }));
    if (errors.aspri_persona) setErrors(prev => ({ ...prev, aspri_persona: '' }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateForm()) return;

    setIsSubmitting(true);
    setIsSuccess(false);

    try {
      const response = await axios.put(
        `${API_BASE_URL}/auth/me`,
        formData,
        { headers: { 'Authorization': `Bearer ${token}` } }
      );

      if (response.data) {
        updateUser(response.data);
        setIsSuccess(true);
        setTimeout(() => setIsSuccess(false), 3000); // Hide success message after 3 seconds
      }
    } catch (error: any) {
      console.error('Update failed:', error);
      alert(t('profile.errors.update_failed') + (error.response?.data?.detail || ''));
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!user) {
    return <div>Loading...</div>; // Or some other loading state
  }

  return (
    <div className="max-w-4xl mx-auto p-4 md:p-6">
      <div className="bg-white dark:bg-zinc-800 rounded-xl shadow-sm p-6">
        <h2 className="text-2xl font-bold text-zinc-900 dark:text-white mb-6">
          {t('profile.title')}
        </h2>

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
              className="w-full px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700"
            />
            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
          </div>

          {/* Birth Date & Month */}
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
                className="w-full px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700"
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
                className="w-full px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700"
              >
                <option value="">{t('register.select_month')}</option>
                {[
                  { value: 1, label: t('months.january') }, { value: 2, label: t('months.february') },
                  { value: 3, label: t('months.march') }, { value: 4, label: t('months.april') },
                  { value: 5, label: t('months.may') }, { value: 6, label: t('months.june') },
                  { value: 7, label: t('months.july') }, { value: 8, label: t('months.august') },
                  { value: 9, label: t('months.september') }, { value: 10, label: t('months.october') },
                  { value: 11, label: t('months.november') }, { value: 12, label: t('months.december') }
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
              className="w-full px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700"
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
              className="w-full px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700"
            />
            {errors.aspri_name && <p className="mt-1 text-sm text-red-500">{errors.aspri_name}</p>}
          </div>

          {/* ASPRI Persona */}
          <div>
            <label htmlFor="aspri_persona" className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
              {t('register.aspri_persona')}
            </label>
            <select
              id="aspri_persona"
              value={personaState.selectedOption}
              onChange={handlePersonaChange}
              className="w-full px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700"
            >
              <option value="">{t('register.aspri_persona_placeholder')}</option>
              {personaOptions.map(option => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
            {personaState.selectedOption === 'custom' && (
              <div className="mt-2">
                <input
                  type="text"
                  value={personaState.customValue}
                  onChange={handleCustomPersonaChange}
                  className="w-full px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700"
                  placeholder={t('register.custom_persona_placeholder')}
                />
              </div>
            )}
            {errors.aspri_persona && <p className="mt-1 text-sm text-red-500">{errors.aspri_persona}</p>}
          </div>

          {/* Submit Button */}
          <div className="flex items-center justify-end gap-4 pt-4">
            {isSuccess && <p className="text-sm text-green-600">{t('profile.success_message')}</p>}
            <button
              type="submit"
              disabled={isSubmitting}
              className="px-6 py-2 bg-brand text-white rounded-lg hover:bg-brand/90 transition font-medium disabled:opacity-50"
            >
              {isSubmitting ? t('profile.saving') : t('profile.save_changes')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
