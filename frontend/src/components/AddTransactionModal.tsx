import React, { useState, useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { createTransaction, getCategories } from '../services/financeService';
import type { FinancialTransactionCreate, FinancialCategory } from '../types/finance';
import { X } from 'lucide-react';

interface AddTransactionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onTransactionAdded: () => void;
}

const AddTransactionModal: React.FC<AddTransactionModalProps> = ({ isOpen, onClose, onTransactionAdded }) => {
  const { t } = useTranslation();
  const { register, handleSubmit, control, reset, formState: { errors } } = useForm<FinancialTransactionCreate>();
  const [categories, setCategories] = useState<FinancialCategory[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen) {
      reset();
      setApiError(null);
      const fetchCategories = async () => {
        try {
          const fetchedCategories = await getCategories();
          setCategories(fetchedCategories);
        } catch (error) {
          console.error('Failed to fetch categories', error);
        }
      };
      fetchCategories();
    }
  }, [isOpen, reset]);

  const onSubmit = async (data: FinancialTransactionCreate) => {
    setIsSubmitting(true);
    setApiError(null);
    try {
      const payload = {
        ...data,
        amount: Number(data.amount),
        category_id: data.category_id ? Number(data.category_id) : undefined,
      };
      await createTransaction(payload);
      onTransactionAdded();
      onClose();
    } catch (error) {
      setApiError(t('finance.error_adding_transaction'));
      console.error(error);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex justify-center items-center p-4">
      <div className="bg-white dark:bg-zinc-800 rounded-lg shadow-xl w-full max-w-md">
        <div className="flex justify-between items-center p-4 border-b dark:border-zinc-700">
          <h2 className="text-lg font-semibold">{t('finance.add_transaction')}</h2>
          <button onClick={onClose} className="p-1 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700">
            <X size={20} />
          </button>
        </div>
        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="p-6 space-y-4">
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{t('finance.date')}</label>
                <input type="date" {...register('date', { required: t('finance.date_required') })} className="w-full px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-brand" />
                {errors.date && <span className="text-red-500 text-sm">{errors.date.message}</span>}
            </div>
             <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{t('finance.description')}</label>
                <input type="text" {...register('description')} placeholder={t('finance.description_placeholder')} className="w-full px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-brand" />
            </div>
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{t('finance.amount')}</label>
                <input type="number" {...register('amount', { required: t('finance.amount_required'), valueAsNumber: true })} placeholder={t('finance.amount_placeholder')} className="w-full px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-brand" />
                {errors.amount && <span className="text-red-500 text-sm">{errors.amount.message}</span>}
            </div>
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{t('finance.type')}</label>
                <Controller
                  name="type"
                  control={control}
                  defaultValue="expense"
                  rules={{ required: t('finance.type_required') }}
                  render={({ field }) => (
                    <select {...field} className="w-full px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-brand">
                      <option value="expense">{t('finance.expense')}</option>
                      <option value="income">{t('finance.income')}</option>
                    </select>
                  )}
                />
                {errors.type && <span className="text-red-500 text-sm">{errors.type.message}</span>}
            </div>
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{t('finance.category')}</label>
                <Controller
                  name="category_id"
                  control={control}
                  render={({ field }) => (
                    <select {...field} className="w-full px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-brand">
                      <option value="">{t('finance.select_category')}</option>
                      {categories.map((category) => (
                        <option key={category.id} value={category.id}>{category.name}</option>
                      ))}
                    </select>
                  )}
                />
            </div>
            {apiError && <p className="text-red-500 text-sm mt-2">{apiError}</p>}
          </div>
          <div className="flex justify-end items-center p-4 bg-gray-50 dark:bg-zinc-800/50 border-t dark:border-zinc-700 rounded-b-lg">
            <button type="button" onClick={onClose} disabled={isSubmitting} className="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 disabled:opacity-50">
              {t('common.cancel')}
            </button>
            <button type="submit" disabled={isSubmitting} className="ml-2 px-4 py-2 text-sm font-medium text-white bg-brand rounded-lg hover:bg-brand/90 disabled:bg-brand/50 disabled:cursor-not-allowed flex items-center">
              {isSubmitting && <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>}
              {t('common.save')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default AddTransactionModal;
