import React, { useState, useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { updateTransaction } from '../services/financeService';
import { getCategories } from '../services/financeService';
import type { FinancialTransaction, FinancialTransactionUpdate } from '../types/finance';
import type { FinancialCategory } from '../types/finance';

interface EditTransactionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onTransactionUpdated: () => void;
  transaction: FinancialTransaction | null;
}

const EditTransactionModal: React.FC<EditTransactionModalProps> = ({ isOpen, onClose, onTransactionUpdated, transaction }) => {
  const { register, handleSubmit, control, reset, formState: { errors } } = useForm<FinancialTransactionUpdate>();
  const [categories, setCategories] = useState<FinancialCategory[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen) {
      const fetchCategories = async () => {
        try {
          const fetchedCategories = await getCategories();
          setCategories(fetchedCategories);
        } catch (error) {
          console.error('Failed to fetch categories', error);
        }
      };
      fetchCategories();
      if (transaction) {
        reset({
          ...transaction,
          date: new Date(transaction.date).toISOString().split('T')[0],
        });
      }
    }
  }, [isOpen, transaction, reset]);

  const onSubmit = async (data: FinancialTransactionUpdate) => {
    if (!transaction) return;
    setIsSubmitting(true);
    setApiError(null);
    try {
      await updateTransaction(transaction.id, data);
      onTransactionUpdated();
      onClose();
    } catch (error) {
      setApiError('Failed to update transaction. Please try again.');
      console.error(error);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="modal modal-open">
      <div className="modal-box">
        <h3 className="font-bold text-lg">Edit Transaksi</h3>
        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="form-control">
            <label className="label"><span className="label-text">Tanggal</span></label>
            <input type="date" {...register('date', { required: true })} className="input input-bordered" />
            {errors.date && <span className="text-red-500">Tanggal harus diisi</span>}
          </div>
          <div className="form-control">
            <label className="label"><span className="label-text">Deskripsi</span></label>
            <input type="text" {...register('description')} className="input input-bordered" />
          </div>
          <div className="form-control">
            <label className="label"><span className="label-text">Jumlah</span></label>
            <input type="number" {...register('amount', { required: true, valueAsNumber: true })} className="input input-bordered" />
            {errors.amount && <span className="text-red-500">Jumlah harus diisi</span>}
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
          <div className="form-control">
            <label className="label"><span className="label-text">Kategori</span></label>
            <Controller
              name="category_id"
              control={control}
              render={({ field }) => (
                <select {...field} className="select select-bordered">
                  <option value="">Pilih Kategori</option>
                  {categories.map((category) => (
                    <option key={category.id} value={category.id}>{category.name}</option>
                  ))}
                </select>
              )}
            />
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

export default EditTransactionModal;
