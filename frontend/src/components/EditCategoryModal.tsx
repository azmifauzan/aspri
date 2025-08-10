import React, { useState, useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { updateCategory } from '../services/financeService';
import type { FinancialCategory, FinancialCategoryUpdate } from '../types/finance';

interface EditCategoryModalProps {
  isOpen: boolean;
  onClose: () => void;
  onCategoryUpdated: () => void;
  category: FinancialCategory | null;
}

const EditCategoryModal: React.FC<EditCategoryModalProps> = ({ isOpen, onClose, onCategoryUpdated, category }) => {
  const { register, handleSubmit, control, reset, formState: { errors } } = useForm<FinancialCategoryUpdate>();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen && category) {
      reset(category);
    }
  }, [isOpen, category, reset]);

  const onSubmit = async (data: FinancialCategoryUpdate) => {
    if (!category) return;
    setIsSubmitting(true);
    setApiError(null);
    try {
      await updateCategory(category.id, data);
      onCategoryUpdated();
      onClose();
    } catch (error) {
      setApiError('Failed to update category. Please try again.');
      console.error(error);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="modal modal-open">
      <div className="modal-box">
        <h3 className="font-bold text-lg">Edit Kategori</h3>
        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="form-control">
            <label className="label"><span className="label-text">Nama</span></label>
            <input type="text" {...register('name', { required: true })} className="input input-bordered" />
            {errors.name && <span className="text-red-500">Nama harus diisi</span>}
          </div>
          <div className="form-control">
            <label className="label"><span className="label-text">Tipe</span></label>
            <Controller
              name="type"
              control={control}
              rules={{ required: true }}
              render={({ field }) => (
                <select {...field} className="select select-bordered">
                  <option value="income">Pemasukan</option>
                  <option value="expense">Pengeluaran</option>
                </select>
              )}
            />
            {errors.type && <span className="text-red-500">Tipe harus dipilih</span>}
          </div>
          {apiError && <p className="text-red-500 mt-4">{apiError}</p>}
          <div className="modal-action">
            <button type="button" onClick={onClose} className="btn">Batal</button>
            <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
              {isSubmitting ? 'Menyimpan...' : 'Simpan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default EditCategoryModal;
