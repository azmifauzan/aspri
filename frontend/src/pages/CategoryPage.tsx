import React, { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { getCategories } from '../services/financeService';
import type { FinancialCategory } from '../types/finance';
import AddCategoryModal from '../components/AddCategoryModal';
import EditCategoryModal from '../components/EditCategoryModal';
import DeleteCategoryModal from '../components/DeleteCategoryModal';
import { Plus, Edit, Trash2, ArrowLeft } from 'lucide-react';

interface CategoryPageProps {
  setActiveItem: (item: string) => void;
}

const CategoryPage: React.FC<CategoryPageProps> = ({ setActiveItem }) => {
  const { t } = useTranslation();
  const [categories, setCategories] = useState<FinancialCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<FinancialCategory | null>(null);

  const fetchCategories = useCallback(async () => {
    setLoading(true);
    try {
      const data = await getCategories();
      setCategories(data);
      setError(null);
    } catch (err) {
      setError(t('finance.error_fetching_categories'));
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, [t]);

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

  return (
    <div className="p-4 md:p-6">
       <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div className="flex items-center">
            <button onClick={() => setActiveItem('finance')} className="p-2 mr-2 text-zinc-500 hover:text-brand dark:hover:text-brand-dark rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700" title={t('finance.back_to_transactions')}>
                <ArrowLeft size={20} />
            </button>
            <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">{t('finance.manage_categories')}</h1>
        </div>
        <div className="flex items-center mt-4 md:mt-0">
          <button onClick={() => setIsAddModalOpen(true)} className="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand rounded-lg hover:bg-brand/90 transition-colors">
            <Plus size={16} />
            {t('finance.add_category')}
          </button>
        </div>
      </div>

      <div className="bg-white dark:bg-zinc-800 rounded-xl shadow-sm">
        <div className="overflow-x-auto">
            {loading ? (
                <p className="p-6 text-center">{t('finance.loading_categories')}</p>
            ) : error ? (
                <p className="p-6 text-center text-red-500">{error}</p>
            ) : (
            <table className="w-full text-sm text-left text-zinc-500 dark:text-zinc-400">
                <thead className="text-xs text-zinc-700 uppercase bg-zinc-50 dark:bg-zinc-700 dark:text-zinc-300">
                <tr>
                    <th scope="col" className="px-6 py-3">{t('finance.category')}</th>
                    <th scope="col" className="px-6 py-3">{t('finance.type')}</th>
                    <th scope="col" className="px-6 py-3 text-center">{t('finance.actions')}</th>
                </tr>
                </thead>
                <tbody>
                {categories.map((category) => (
                    <tr key={category.id} className="bg-white dark:bg-zinc-800 border-b dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                        <td className="px-6 py-4 font-medium text-zinc-900 dark:text-white">{category.name}</td>
                        <td className="px-6 py-4">{t(`finance.${category.type}`)}</td>
                        <td className="px-6 py-4 text-center">
                            <button onClick={() => handleEditClick(category)} className="p-2 text-zinc-500 hover:text-brand dark:hover:text-brand-dark rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700">
                                <Edit size={16} />
                            </button>
                            <button onClick={() => handleDeleteClick(category)} className="p-2 text-zinc-500 hover:text-red-500 dark:hover:text-red-500 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-700 ml-2">
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
    </div>
  );
};

export default CategoryPage;
