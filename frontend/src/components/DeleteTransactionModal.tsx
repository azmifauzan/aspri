import React, { useState } from 'react';
import { deleteTransaction } from '../services/financeService';
import type { FinancialTransaction } from '../types/finance';

interface DeleteTransactionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onTransactionDeleted: () => void;
  transaction: FinancialTransaction | null;
}

const DeleteTransactionModal: React.FC<DeleteTransactionModalProps> = ({ isOpen, onClose, onTransactionDeleted, transaction }) => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);

  const handleDelete = async () => {
    if (!transaction) return;
    setIsSubmitting(true);
    setApiError(null);
    try {
      await deleteTransaction(transaction.id);
      onTransactionDeleted();
      onClose();
    } catch (error) {
      setApiError('Failed to delete transaction. Please try again.');
      console.error(error);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="modal modal-open">
      <div className="modal-box">
        <h3 className="font-bold text-lg">Hapus Transaksi</h3>
        <p className="py-4">Apakah Anda yakin ingin menghapus transaksi ini?</p>
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

export default DeleteTransactionModal;
