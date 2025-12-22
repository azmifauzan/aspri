// src/components/DeleteContactModal.tsx
import { useTranslation } from 'react-i18next';
import { X, AlertTriangle } from 'lucide-react';
import type { Contact } from '../services/contactService';

interface DeleteContactModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  contact: Contact | null;
  isDeleting: boolean;
}

export default function DeleteContactModal({ isOpen, onClose, onConfirm, contact, isDeleting }: DeleteContactModalProps) {
  const { t } = useTranslation();

  if (!isOpen) {
    return null;
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex justify-center items-center p-4">
      <div className="bg-white dark:bg-zinc-800 rounded-lg shadow-xl w-full max-w-md">
        <div className="flex justify-between items-center p-4 border-b dark:border-zinc-700">
          <h2 className="text-lg font-semibold text-red-600 dark:text-red-400">{t('contacts.delete_contact')}</h2>
          <button onClick={onClose} className="p-1 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700">
            <X size={20} />
          </button>
        </div>
        <div className="p-6">
          <div className="flex items-start">
            <div className="flex-shrink-0 h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center">
              <AlertTriangle className="h-6 w-6 text-red-600 dark:text-red-400" />
            </div>
            <div className="ml-4">
              <p className="text-sm text-zinc-600 dark:text-zinc-300">
                {t('contacts.delete_confirmation_message', { name: contact?.name })}
              </p>
            </div>
          </div>
        </div>
        <div className="flex justify-end items-center p-4 bg-gray-50 dark:bg-zinc-800/50 border-t dark:border-zinc-700 rounded-b-lg">
          <button
            type="button"
            onClick={onClose}
            disabled={isDeleting}
            className="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 disabled:opacity-50"
          >
            {t('common.cancel')}
          </button>
          <button
            onClick={onConfirm}
            disabled={isDeleting}
            className="ml-2 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:bg-red-400 disabled:cursor-not-allowed flex items-center"
          >
            {isDeleting && <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>}
            {t('common.delete')}
          </button>
        </div>
      </div>
    </div>
  );
}
