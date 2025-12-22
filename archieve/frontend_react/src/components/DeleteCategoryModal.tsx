import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { deleteCategory } from '../services/financeService';
import type { FinancialCategory } from '../types/finance';
import { X, AlertTriangle } from 'lucide-react';

interface DeleteCategoryModalProps {
  isOpen: boolean;
  onClose: () => void;
  onCategoryDeleted: () => void;
  category: FinancialCategory | null;
}

const DeleteCategoryModal: React.FC<DeleteCategoryModalProps> = ({ isOpen, onClose, onCategoryDeleted, category }) => {
  const { t } = useTranslation();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen) {
      setApiError(null);
    }
  }, [isOpen]);

  const handleDelete = async () => {
    if (!category) return;
    setIsSubmitting(true);
    setApiError(null);
    try {
      await deleteCategory(category.id);
      onCategoryDeleted();
      onClose();
    } catch (error) {
      setApiError(t('finance.error_deleting_category'));
      console.error(error);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-[60] flex justify-center items-center p-4">
      <div className="bg-white dark:bg-zinc-800 rounded-lg shadow-xl w-full max-w-md">
        <div className="flex justify-between items-center p-4 border-b dark:border-zinc-700">
          <h2 className="text-lg font-semibold flex items-center">
            <AlertTriangle className="text-red-500 mr-2" size={20} />
            {t('finance.delete_category')}
          </h2>
          <button onClick={onClose} className="p-1 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700">
            <X size={20} />
          </button>
        </div>
        <div className="p-6">
          <p>{t('finance.confirm_delete_category')}</p>
          <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
            "{category?.name || t('finance.category')}"
          </p>
          {apiError && <p className="text-red-500 text-sm mt-4">{apiError}</p>}
        </div>
        <div className="flex justify-end items-center p-4 bg-gray-50 dark:bg-zinc-800/50 border-t dark:border-zinc-700 rounded-b-lg">
            <button type="button" onClick={onClose} disabled={isSubmitting} className="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 disabled:opacity-50">
              {t('common.cancel')}
            </button>
            <button onClick={handleDelete} disabled={isSubmitting} className="ml-2 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:bg-red-400 disabled:cursor-not-allowed flex items-center">
              {isSubmitting && <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>}
              {t('common.delete')}
            </button>
        </div>
      </div>
    </div>
  );
};

export default DeleteCategoryModal;
