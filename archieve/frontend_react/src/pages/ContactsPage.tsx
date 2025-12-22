// src/pages/ContactsPage.tsx
import { useState, useEffect, useMemo, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { getContacts, createContact, updateContact, deleteContact } from '../services/contactService';
import type { Contact } from '../services/contactService';
import { PlusCircle, Edit, Trash2, User, Mail, Phone, Search, X } from 'lucide-react';
import AddContactModal from '../components/AddContactModal';
import EditContactModal from '../components/EditContactModal';
import DeleteContactModal from '../components/DeleteContactModal';
import ViewContactModal from '../components/ViewContactModal';

type ModalState = {
  type: 'add' | 'edit' | 'delete' | 'view' | null;
  contact: Contact | null;
}

export default function ContactsPage() {
  const { t } = useTranslation();
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [modal, setModal] = useState<ModalState>({ type: null, contact: null });
  const [isSaving, setIsSaving] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  const fetchContacts = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const fetchedContacts = await getContacts();
      setContacts(fetchedContacts);
    } catch (err) {
      setError(t('contacts.error_fetching'));
      console.error(err);
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    fetchContacts();
  }, [fetchContacts]);

  const handleAddClick = () => setModal({ type: 'add', contact: null });
  const handleViewClick = (contact: Contact) => setModal({ type: 'view', contact });
  const handleEditClick = (contact: Contact) => setModal({ type: 'edit', contact });
  const handleDeleteClick = (contact: Contact) => setModal({ type: 'delete', contact });
  const handleCloseModal = () => setModal({ type: null, contact: null });

  const handleCreateContact = async (contactData: Omit<Contact, 'id' | 'etag'>) => {
    setIsSaving(true);
    setError(null);
    try {
      const payload = {
        ...contactData,
        email: contactData.email || undefined,
        phone: contactData.phone || undefined,
      };
      await createContact(payload);
      await fetchContacts(); // Refetch to get the latest list with new contact
      handleCloseModal();
    } catch (err) {
      setError(t('contacts.error_creating'));
      console.error(err);
    } finally {
      setIsSaving(false);
    }
  };

  const handleUpdateContact = async (updatedContact: Contact) => {
    setIsSaving(true);
    setError(null);
    try {
      const payload = {
        ...updatedContact,
        email: updatedContact.email || undefined,
        phone: updatedContact.phone || undefined,
      };
      await updateContact(payload.id, payload);
      await fetchContacts(); // Refetch to get updated etag and info
      handleCloseModal();
    } catch (err) {
      setError(t('contacts.error_updating'));
      console.error(err);
    } finally {
      setIsSaving(false);
    }
  };

  const handleDeleteConfirm = async () => {
    if (!modal.contact) return;
    setIsDeleting(true);
    setError(null);
    try {
      await deleteContact(modal.contact.id);
      setContacts(contacts.filter(c => c.id !== modal.contact?.id));
      handleCloseModal();
    } catch (err) {
      setError(t('contacts.error_deleting'));
      console.error(err);
    } finally {
      setIsDeleting(false);
    }
  };

  const filteredContacts = useMemo(() => {
    return contacts.filter(contact =>
      contact.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (contact.email && contact.email.toLowerCase().includes(searchTerm.toLowerCase()))
    );
  }, [contacts, searchTerm]);

  return (
    <div className="p-4 md:p-6 bg-gray-50 dark:bg-zinc-900">
      <div className="max-w-4xl mx-auto">
        <div className="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
          <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">
            {t('contacts.title')}
          </h1>
          <div className="flex items-center gap-2 w-full md:w-auto">
            <div className="relative w-full md:w-64">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" size={20} />
              <input
                type="text"
                placeholder={t('contacts.search')}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-10 py-2 border border-zinc-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-brand"
              />
              {searchTerm && (
                <button
                  onClick={() => setSearchTerm('')}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                  aria-label={t('common.clear_search')}
                >
                  <X size={18} />
                </button>
              )}
            </div>
            <button
              onClick={handleAddClick}
              className="flex items-center gap-2 bg-brand text-white px-4 py-2 rounded-lg hover:bg-brand/90 transition-colors"
            >
              <PlusCircle size={20} />
              <span className="hidden sm:inline">{t('contacts.add')}</span>
            </button>
          </div>
        </div>

        {error && <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
          <span className="block sm:inline">{error}</span>
          <button className="absolute top-0 bottom-0 right-0 px-4 py-3" onClick={() => setError(null)}>
            <X size={20} className="text-red-700" />
          </button>
        </div>}

        {isLoading ? <p className="text-center p-4">{t('contacts.loading')}</p> : (
          <div className="bg-white dark:bg-zinc-800 rounded-lg shadow">
            <ul className="divide-y divide-gray-200 dark:divide-zinc-700">
              {filteredContacts.length > 0 ? (
                filteredContacts.map((contact) => (
                  <li key={contact.id} className="p-4 flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div
                      className="flex-1 mb-4 md:mb-0 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50 -m-4 p-4 rounded-lg"
                      onClick={() => handleViewClick(contact)}
                    >
                      <div className="flex items-center gap-3 mb-2">
                        <User className="w-5 h-5 text-zinc-500" />
                        <p className="font-semibold text-zinc-900 dark:text-white">{contact.name}</p>
                      </div>
                      {contact.email && (
                        <div className="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400 mb-1">
                          <Mail className="w-5 h-5" />
                          <span>{contact.email}</span>
                        </div>
                      )}
                      {contact.phone && (
                        <div className="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                          <Phone className="w-5 h-5" />
                          <span>{contact.phone}</span>
                        </div>
                      )}
                    </div>
                    <div className="flex items-center gap-2 pl-4">
                      <button
                        onClick={(e) => { e.stopPropagation(); handleEditClick(contact); }}
                        className="p-2 text-zinc-500 hover:text-brand dark:hover:text-brand-dark rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700"
                        aria-label={t('contacts.edit')}
                      >
                        <Edit size={18} />
                      </button>
                      <button
                        onClick={(e) => { e.stopPropagation(); handleDeleteClick(contact); }}
                        className="p-2 text-red-500 hover:text-red-700 dark:hover:text-red-400 rounded-md hover:bg-red-50 dark:hover:bg-zinc-700"
                        aria-label={t('contacts.delete')}
                      >
                        <Trash2 size={18} />
                      </button>
                    </div>
                  </li>
                ))
              ) : (
                <li className="p-4 text-center text-zinc-500 dark:text-zinc-400">
                  {searchTerm ? t('contacts.no_results') : t('contacts.no_contacts')}
                </li>
              )}
            </ul>
          </div>
        )}
      </div>

      <ViewContactModal isOpen={modal.type === 'view'} onClose={handleCloseModal} contact={modal.contact} />
      <AddContactModal isOpen={modal.type === 'add'} onClose={handleCloseModal} onSave={handleCreateContact} isSaving={isSaving} />
      <EditContactModal isOpen={modal.type === 'edit'} onClose={handleCloseModal} onSave={handleUpdateContact} contact={modal.contact} isSaving={isSaving} />
      <DeleteContactModal isOpen={modal.type === 'delete'} onClose={handleCloseModal} onConfirm={handleDeleteConfirm} contact={modal.contact} isDeleting={isDeleting} />
    </div>
  );
}
