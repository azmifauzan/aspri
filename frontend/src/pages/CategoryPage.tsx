import React, { useState, useEffect, useCallback } from 'react';
import { getCategories } from '../services/financeService';
import type { FinancialCategory } from '../types/finance';
import AddCategoryModal from '../components/AddCategoryModal';
import EditCategoryModal from '../components/EditCategoryModal';
import DeleteCategoryModal from '../components/DeleteCategoryModal';

const CategoryPage: React.FC = () => {
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
    fetchCategories();
  }, [fetchCategories]);

  const handleEditClick = (category: FinancialCategory) => {
    setSelectedCategory(category);
    setIsEditModalOpen(true);
  };

  const handleDeleteClick = (category: FinancialCategory) => {
    setSelectedCategory(category);
    setIsDeleteModalOpen(true);
  };

  return (
    <div className="container mx-auto p-4">
      <h1 className="text-2xl font-bold mb-4">Manajemen Kategori</h1>
      <div className="flex justify-end mb-4">
        <button onClick={() => setIsAddModalOpen(true)} className="btn btn-primary">Tambah Kategori</button>
      </div>
      {loading && <p>Loading...</p>}
      {error && <p className="text-red-500">{error}</p>}
      {!loading && !error && (
        <div className="overflow-x-auto">
          <table className="table w-full">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Tipe</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {categories.map((category) => (
                <tr key={category.id}>
                  <td>{category.name}</td>
                  <td>{category.type === 'income' ? 'Pemasukan' : 'Pengeluaran'}</td>
                  <td>
                    <button onClick={() => handleEditClick(category)} className="btn btn-sm btn-outline">Edit</button>
                    <button onClick={() => handleDeleteClick(category)} className="btn btn-sm btn-outline btn-error ml-2">Hapus</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <AddCategoryModal
        isOpen={isAddModalOpen}
        onClose={() => setIsAddModalOpen(false)}
        onCategoryAdded={fetchCategories}
      />
      <EditCategoryModal
        isOpen={isEditModalOpen}
        onClose={() => setIsEditModalOpen(false)}
        onCategoryUpdated={fetchCategories}
        category={selectedCategory}
      />
      <DeleteCategoryModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onCategoryDeleted={fetchCategories}
        category={selectedCategory}
      />
    </div>
  );
};

export default CategoryPage;
