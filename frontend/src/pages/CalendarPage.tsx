import React from 'react';
import { useTranslation } from 'react-i18next';
import Calendar from '../components/Calendar';
import DashboardLayout from '../components/DashboardLayout';

const CalendarPage: React.FC = () => {
    const { t } = useTranslation();
    return (
        <DashboardLayout title={t('dashboard.menu.calendar')}>
            <div className="max-w-6xl mx-auto">
                <h1 className="text-2xl font-bold mb-4">{t('dashboard.menu.calendar')}</h1>
                <Calendar />
            </div>
        </DashboardLayout>
    );
};

export default CalendarPage;
