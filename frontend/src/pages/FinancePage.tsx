import React, { useState, useEffect, useCallback } from 'react';
import { getTransactions, getCategories } from '../services/financeService';
import type { FinancialTransaction, FinancialCategory } from '../types/finance';
import AddTransactionModal from '../components/AddTransactionModal';
import EditTransactionModal from '../components/EditTransactionModal';
import DeleteTransactionModal from '../components/DeleteTransactionModal';
import { Plus, Settings, Edit, Trash2 } from 'lucide-react';

interface FinancePageProps {
    setActiveItem: (item: string) => void;
}

const FinancePage: React.FC<FinancePageProps> = ({ setActiveItem }) => {
  const [transactions, setTransactions] = useState<FinancialTransaction[]>([]);
  const [categories, setCategories] = useState<FinancialCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [selectedTransaction, setSelectedTransaction] = useState<FinancialTransaction | null>(null);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
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
    fetchData();
  }, [fetchData]);

  const handleEditClick = (transaction: FinancialTransaction) => {
    setSelectedTransaction(transaction);
    setIsEditModalOpen(true);
  };

  const handleDeleteClick = (transaction: FinancialTransaction) => {
    setSelectedTransaction(transaction);
    setIsDeleteModalOpen(true);
  };

  const getCategoryName = (categoryId: number | undefined) => {
    if (categoryId === undefined) return <span className="text-zinc-500">N/A</span>;
    const category = categories.find((c) => c.id === categoryId);
    return category ? category.name : <span className="text-zinc-500">Uncategorized</span>;
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
  }

  return (
    <div className="p-4 md:p-6">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">Mutasi Keuangan</h1>
        <div className="flex items-center mt-4 md:mt-0">
          <button onClick={() => setActiveItem('categories')} className="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 border dark:border-zinc-700 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors mr-2">
            <Settings size={16} />
            Manajemen Kategori
          </button>
          <button onClick={() => setIsAddModalOpen(true)} className="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand rounded-lg hover:bg-brand/90 transition-colors">
            <Plus size={16} />
            Tambah Transaksi
          </button>
        </div>
      </div>

      <div className="bg-white dark:bg-zinc-800 rounded-xl shadow-sm">
        <div className="overflow-x-auto">
            {loading ? (
                <p className="p-6 text-center">Loading transactions...</p>
            ) : error ? (
                <p className="p-6 text-center text-red-500">{error}</p>
            ) : (
            <table className="w-full text-sm text-left text-zinc-500 dark:text-zinc-400">
                <thead className="text-xs text-zinc-700 uppercase bg-zinc-50 dark:bg-zinc-700 dark:text-zinc-300">
                <tr>
                    <th scope="col" className="px-6 py-3">Tanggal</th>
                    <th scope="col" className="px-6 py-3">Deskripsi</th>
                    <th scope="col" className="px-6 py-3">Kategori</th>
                    <th scope="col" className="px-6 py-3 text-right">Jumlah</th>
                    <th scope="col" className="px-6 py-3 text-center">Aksi</th>
                </tr>
                </thead>
                <tbody>
                {transactions.map((transaction) => (
                    <tr key={transaction.id} className="bg-white dark:bg-zinc-800 border-b dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                        <td className="px-6 py-4">{new Date(transaction.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })}</td>
                        <td className="px-6 py-4 font-medium text-zinc-900 dark:text-white">{transaction.description}</td>
                        <td className="px-6 py-4">{getCategoryName(transaction.category_id)}</td>
                        <td className={`px-6 py-4 text-right font-medium ${transaction.type === 'income' ? 'text-green-600' : 'text-red-600'}`}>
                            {transaction.type === 'income' ? '+' : '-'} {formatCurrency(transaction.amount)}
                        </td>
                        <td className="px-6 py-4 text-center">
                            <button onClick={() => handleEditClick(transaction)} className="p-2 text-zinc-500 hover:text-brand dark:hover:text-brand-dark rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700">
                                <Edit size={16} />
                            </button>
                            <button onClick={() => handleDeleteClick(transaction)} className="p-2 text-zinc-500 hover:text-red-500 dark:hover:text-red-500 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700 ml-2">
                                <Trash2 size={16} />
                            </button>
                        </td>
                    </tr>
                ))}
                </tbody>
            </table>
            )}
        </div>
      </div>

      <AddTransactionModal
        isOpen={isAddModalOpen}
        onClose={() => setIsAddModalOpen(false)}
        onTransactionAdded={fetchData}
      />
      <EditTransactionModal
        isOpen={isEditModalOpen}
        onClose={() => setIsEditModalOpen(false)}
        onTransactionUpdated={fetchData}
        transaction={selectedTransaction}
      />
      <DeleteTransactionModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onTransactionDeleted={fetchData}
        transaction={selectedTransaction}
      />
    </div>
  );
};

export default FinancePage;
