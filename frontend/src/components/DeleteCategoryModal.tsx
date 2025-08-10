import React, { useState } from 'react';
import { deleteCategory } from '../services/financeService';
import type { FinancialCategory } from '../types/finance';

interface DeleteCategoryModalProps {
  isOpen: boolean;
  onClose: () => void;
  onCategoryDeleted: () => void;
  category: FinancialCategory | null;
}

const DeleteCategoryModal: React.FC<DeleteCategoryModalProps> = ({ isOpen, onClose, onCategoryDeleted, category }) => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);

  const handleDelete = async () => {
    if (!category) return;
    setIsSubmitting(true);
    setApiError(null);
    try {
      await deleteCategory(category.id);
      onCategoryDeleted();
      onClose();
    } catch (error) {
      setApiError('Failed to delete category. Please try again.');
      console.error(error);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="modal modal-open">
      <div className="modal-box">
        <h3 className="font-bold text-lg">Hapus Kategori</h3>
        <p className="py-4">Apakah Anda yakin ingin menghapus kategori ini?</p>
        {apiError && <p className="text-red-500 mt-4">{apiError}</p>}
        <div className="modal-action">
          <button type="button" onClick={onClose} className="btn">Batal</button>
          <button onClick={handleDelete} className="btn btn-error" disabled={isSubmitting}>
            {isSubmitting ? 'Menghapus...' : 'Hapus'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default DeleteCategoryModal;
