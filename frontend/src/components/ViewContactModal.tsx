// src/components/ViewContactModal.tsx
import { useTranslation } from 'react-i18next';
import { X, Mail, Phone } from 'lucide-react';
import type { Contact } from '../services/contactService';

interface ViewContactModalProps {
  isOpen: boolean;
  onClose: () => void;
  contact: Contact | null;
}

export default function ViewContactModal({ isOpen, onClose, contact }: ViewContactModalProps) {
  const { t } = useTranslation();

  if (!isOpen || !contact) {
    return null;
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex justify-center items-center p-4">
      <div className="bg-white dark:bg-zinc-800 rounded-lg shadow-xl w-full max-w-md">
        <div className="flex justify-between items-center p-4 border-b dark:border-zinc-700">
          <h2 className="text-lg font-semibold">{t('contacts.contact_details')}</h2>
          <button onClick={onClose} className="p-1 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700">
            <X size={20} />
          </button>
        </div>
        <div className="p-6 space-y-4">
          <div className="flex items-center gap-4">
            <div className="w-16 h-16 rounded-full bg-brand flex items-center justify-center text-white font-bold text-2xl">
              {contact.name.charAt(0).toUpperCase()}
            </div>
            <h3 className="text-xl font-bold text-zinc-900 dark:text-white">{contact.name}</h3>
          </div>
          <div className="space-y-2 pt-4">
            {contact.email && (
              <div className="flex items-center gap-3 text-zinc-700 dark:text-zinc-300">
                <Mail className="w-5 h-5 text-zinc-400" />
                <span>{contact.email}</span>
              </div>
            )}
            {contact.phone && (
              <div className="flex items-center gap-3 text-zinc-700 dark:text-zinc-300">
                <Phone className="w-5 h-5 text-zinc-400" />
                <span>{contact.phone}</span>
              </div>
            )}
            {!contact.email && !contact.phone && (
              <p className="text-sm text-zinc-500 dark:text-zinc-400">{t('contacts.no_additional_info')}</p>
            )}
          </div>
        </div>
        <div className="flex justify-end items-center p-4 bg-gray-50 dark:bg-zinc-800/50 border-t dark:border-zinc-700 rounded-b-lg">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-white bg-brand rounded-lg hover:bg-brand/90"
          >
            {t('common.close')}
          </button>
        </div>
      </div>
    </div>
  );
}
