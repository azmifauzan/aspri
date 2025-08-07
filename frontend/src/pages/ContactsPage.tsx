// src/pages/ContactsPage.tsx
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { getContacts, createContact, updateContact, deleteContact, Contact } from '../services/contactService';
import { PlusCircle, Edit, Trash2, User, Mail, Phone } from 'lucide-react';

export default function ContactsPage() {
  const { t } = useTranslation();
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchContacts = async () => {
      try {
        setIsLoading(true);
        const fetchedContacts = await getContacts();
        setContacts(fetchedContacts);
        setError(null);
      } catch (err) {
        setError(t('contacts.error_fetching'));
        console.error(err);
      } finally {
        setIsLoading(false);
      }
    };

    fetchContacts();
  }, [t]);

  // Placeholder functions for CRUD operations
  const handleAddContact = () => {
    // TODO: Implement create contact modal
    alert('Add contact functionality not implemented yet.');
  };

  const handleEditContact = (contact: Contact) => {
    // TODO: Implement edit contact modal
    alert(`Edit contact: ${contact.name}`);
  };

  const handleDeleteContact = async (resourceName: string) => {
    if (window.confirm(t('contacts.confirm_delete'))) {
      try {
        await deleteContact(resourceName);
        setContacts(contacts.filter(c => c.id !== resourceName));
      } catch (err) {
        setError(t('contacts.error_deleting'));
        console.error(err);
      }
    }
  };

  return (
    <div className="p-4 md:p-6 bg-gray-50 dark:bg-zinc-900 h-full">
      <div className="max-w-4xl mx-auto">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">
            {t('contacts.title')}
          </h1>
          <button
            onClick={handleAddContact}
            className="flex items-center gap-2 bg-brand text-white px-4 py-2 rounded-lg hover:bg-brand/90 transition-colors"
          >
            <PlusCircle size={20} />
            <span>{t('contacts.add')}</span>
          </button>
        </div>

        {isLoading && <p>{t('contacts.loading')}</p>}
        {error && <p className="text-red-500">{error}</p>}

        {!isLoading && !error && (
          <div className="bg-white dark:bg-zinc-800 rounded-lg shadow">
            <ul className="divide-y divide-gray-200 dark:divide-zinc-700">
              {contacts.length > 0 ? (
                contacts.map((contact) => (
                  <li key={contact.id} className="p-4 flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div className="flex-1 mb-4 md:mb-0">
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
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => handleEditContact(contact)}
                        className="p-2 text-zinc-500 hover:text-brand dark:hover:text-brand-dark rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700"
                        aria-label={t('contacts.edit')}
                      >
                        <Edit size={18} />
                      </button>
                      <button
                        onClick={() => handleDeleteContact(contact.id)}
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
                  {t('contacts.no_contacts')}
                </li>
              )}
            </ul>
          </div>
        )}
      </div>
    </div>
  );
}
