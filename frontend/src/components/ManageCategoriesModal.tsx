import React, { useState, useEffect, useCallback } from 'react';
import { getCategories } from '../services/financeService';
import type { FinancialCategory } from '../types/finance';
import { X } from 'lucide-react';
import AddCategoryModal from './AddCategoryModal';
import EditCategoryModal from './EditCategoryModal';
import DeleteCategoryModal from './DeleteCategoryModal';

interface ManageCategoriesModalProps {
  isOpen: boolean;
  onClose: () => void;
}

const ManageCategoriesModal: React.FC<ManageCategoriesModalProps> = ({ isOpen, onClose }) => {
  const [categories, setCategories] = useState<FinancialCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<FinancialCategory | null>(null);

  const fetchCategories = useCallback(async () => {
    try {
      setLoading(true);
      const data = await getCategories();
      setCategories(data);
      setError(null);
    } catch (err) {
      setError('Failed to fetch categories');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isOpen) {
      fetchCategories();
    }
  }, [isOpen, fetchCategories]);

  const handleEditClick = (category: FinancialCategory) => {
    setSelectedCategory(category);
    setIsEditModalOpen(true);
  };

  const handleDeleteClick = (category: FinancialCategory) => {
    setSelectedCategory(category);
    setIsDeleteModalOpen(true);
  };

  const handleCategoryAdded = () => {
    fetchCategories();
    setIsAddModalOpen(false);
  }

  const handleCategoryUpdated = () => {
    fetchCategories();
    setIsEditModalOpen(false);
  }

  const handleCategoryDeleted = () => {
    fetchCategories();
    setIsDeleteModalOpen(false);
  }

  if (!isOpen) {
    return null;
  }

  return (
    <>
      <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex justify-center items-center p-4">
        <div className="bg-white dark:bg-zinc-800 rounded-lg shadow-xl w-full max-w-lg">
          <div className="flex justify-between items-center p-4 border-b dark:border-zinc-700">
            <h2 className="text-lg font-semibold">Manajemen Kategori</h2>
            <button onClick={onClose} className="p-1 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700">
              <X size={20} />
            </button>
          </div>
          <div className="p-6">
            <div className="flex justify-end mb-4">
              <button onClick={() => setIsAddModalOpen(true)} className="btn btn-primary btn-sm">Tambah Kategori</button>
            </div>
            {loading && <p>Loading...</p>}
            {error && <p className="text-red-500">{error}</p>}
            {!loading && !error && (
              <div className="overflow-y-auto max-h-96">
                <table className="table w-full">
                  <thead className="sticky top-0 bg-white dark:bg-zinc-800">
                    <tr>
                      <th>Nama</th>
                      <th>Tipe</th>
                      <th className="text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    {categories.map((category) => (
                      <tr key={category.id}>
                        <td>{category.name}</td>
                        <td>{category.type === 'income' ? 'Pemasukan' : 'Pengeluaran'}</td>
                        <td className="text-right">
                          <button onClick={() => handleEditClick(category)} className="btn btn-sm btn-outline mr-2">Edit</button>
                          <button onClick={() => handleDeleteClick(category)} className="btn btn-sm btn-outline btn-error">Hapus</button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
          <div className="flex justify-end items-center p-4 bg-gray-50 dark:bg-zinc-800/50 border-t dark:border-zinc-700 rounded-b-lg">
              <button onClick={onClose} className="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700">
                Tutup
              </button>
          </div>
        </div>
      </div>
      <AddCategoryModal
        isOpen={isAddModalOpen}
        onClose={() => setIsAddModalOpen(false)}
        onCategoryAdded={handleCategoryAdded}
      />
      <EditCategoryModal
        isOpen={isEditModalOpen}
        onClose={() => setIsEditModalOpen(false)}
        onCategoryUpdated={handleCategoryUpdated}
        category={selectedCategory}
      />
      <DeleteCategoryModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onCategoryDeleted={handleCategoryDeleted}
        category={selectedCategory}
      />
    </>
  );
};

export default ManageCategoriesModal;
