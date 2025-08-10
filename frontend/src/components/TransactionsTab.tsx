import React, { useState, useEffect, useCallback } from 'react';
import { getTransactions, getCategories } from '../services/financeService';
import type { FinancialTransaction, FinancialCategory } from '../types/finance';
import AddTransactionModal from './AddTransactionModal';
import EditTransactionModal from './EditTransactionModal';
import DeleteTransactionModal from './DeleteTransactionModal';

const TransactionsTab: React.FC = () => {
  const [transactions, setTransactions] = useState<FinancialTransaction[]>([]);
  const [categories, setCategories] = useState<FinancialCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [selectedTransaction, setSelectedTransaction] = useState<FinancialTransaction | null>(null);

  const fetchTransactions = useCallback(async () => {
    try {
      setLoading(true);
      const [transactionsData, categoriesData] = await Promise.all([
        getTransactions(),
        getCategories(),
      ]);
      setTransactions(transactionsData);
      setCategories(categoriesData);
      setError(null);
    } catch (err) {
      setError('Failed to fetch data');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchTransactions();
  }, [fetchTransactions]);

  const handleEditClick = (transaction: FinancialTransaction) => {
    setSelectedTransaction(transaction);
    setIsEditModalOpen(true);
  };

  const handleDeleteClick = (transaction: FinancialTransaction) => {
    setSelectedTransaction(transaction);
    setIsDeleteModalOpen(true);
  };

  const getCategoryName = (categoryId: number | undefined) => {
    if (categoryId === undefined) return 'N/A';
    const category = categories.find((c) => c.id === categoryId);
    return category ? category.name : 'Uncategorized';
  };

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-xl font-bold">Mutasi</h2>
        <button onClick={() => setIsAddModalOpen(true)} className="btn btn-primary">Tambah Transaksi</button>
      </div>
      {loading && <p>Loading...</p>}
      {error && <p className="text-red-500">{error}</p>}
      {!loading && !error && (
        <div className="overflow-x-auto">
          <table className="table w-full">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Deskripsi</th>
                <th>Kategori</th>
                <th>Tipe</th>
                <th>Jumlah</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {transactions.map((transaction) => (
                <tr key={transaction.id}>
                  <td>{new Date(transaction.date).toLocaleDateString('id-ID')}</td>
                  <td>{transaction.description}</td>
                  <td>{getCategoryName(transaction.category_id)}</td>
                  <td>{transaction.type === 'income' ? 'Pemasukan' : 'Pengeluaran'}</td>
                  <td>Rp {transaction.amount.toLocaleString('id-ID')}</td>
                  <td>
                    <button onClick={() => handleEditClick(transaction)} className="btn btn-sm btn-outline">Edit</button>
                    <button onClick={() => handleDeleteClick(transaction)} className="btn btn-sm btn-outline btn-error ml-2">Hapus</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <AddTransactionModal
        isOpen={isAddModalOpen}
        onClose={() => setIsAddModalOpen(false)}
        onTransactionAdded={fetchTransactions}
      />
      <EditTransactionModal
        isOpen={isEditModalOpen}
        onClose={() => setIsEditModalOpen(false)}
        onTransactionUpdated={fetchTransactions}
        transaction={selectedTransaction}
      />
      <DeleteTransactionModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onTransactionDeleted={fetchTransactions}
        transaction={selectedTransaction}
      />
    </div>
  );
};

export default TransactionsTab;
