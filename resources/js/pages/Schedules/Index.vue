<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ref, computed } from 'vue';
import { Plus, Pencil, Trash, List, Calendar as CalendarIcon, ChevronLeft, ChevronRight, MapPin, Clock } from 'lucide-vue-next';
import * as scheduleRoutes from '@/routes/schedules';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';
import type { BreadcrumbItem } from '@/types';

interface Schedule {
    id: number;
    title: string;
    description: string | null;
    start_time: string;
    end_time: string;
    location: string | null;
}

const props = defineProps<{
    schedules: Schedule[];
}>();

const { t, locale, tm, rt } = useI18n();

const isDialogOpen = ref(false);
const isEditing = ref(false);
const editingId = ref<number | null>(null);
const viewMode = ref<'list' | 'calendar'>('calendar');
const currentMonth = ref(new Date());

const form = useForm({
    title: '',
    description: '',
    start_time: '',
    end_time: '',
    location: '',
});

// Calendar Logic
const weekDays = computed(() => {
    const msgs = tm('schedules.weekDays');
    return Array.isArray(msgs) ? msgs.map((m) => rt(m)) : [];
});

const calendarDays = computed(() => {
    const year = currentMonth.value.getFullYear();
    const month = currentMonth.value.getMonth();
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    
    const days = [];
    
    // Padding for previous month
    for (let i = 0; i < firstDay.getDay(); i++) {
        days.push({
            date: new Date(year, month, -i),
            isCurrentMonth: false,
            events: [] as Schedule[]
        });
    }
    // Reverse padding to get correct order
    const paddingCount = days.length;
    days.splice(0, days.length, ...days.reverse());
    
    // Current month days
    for (let i = 1; i <= lastDay.getDate(); i++) {
        const date = new Date(year, month, i);
        days.push({
            date: date,
            isCurrentMonth: true,
            events: props.schedules.filter(s => {
                const sDate = new Date(s.start_time);
                return sDate.getDate() === i && 
                       sDate.getMonth() === month && 
                       sDate.getFullYear() === year;
            })
        });
    }

    // Padding for next month to complete the grid (optional, usually 35 or 42 cells)
    const remainingCells = 42 - days.length; // 6 rows * 7 days
    for (let i = 1; i <= remainingCells; i++) {
        days.push({
            date: new Date(year, month + 1, i),
            isCurrentMonth: false,
            events: [] as Schedule[]
        });
    }

    return days;
});

const monthName = computed(() => {
    return currentMonth.value.toLocaleString(locale.value === 'id' ? 'id-ID' : 'en-US', { month: 'long', year: 'numeric' });
});

const prevMonth = () => {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 1);
};

// CRUD Logic
const openCreateDialog = (date?: Date) => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    
    if (date) {
        // Set default time to 09:00 on selected date
        const d = new Date(date);
        d.setHours(9, 0, 0, 0);
        // Correctly format for datetime-local which requires local time string
        // but toISOString returns UTC. Let's do simple formatting.
        const formatLocal = (inputDate: Date) => {
             const pad = (n: number) => n < 10 ? '0' + n : n;
             return `${inputDate.getFullYear()}-${pad(inputDate.getMonth()+1)}-${pad(inputDate.getDate())}T${pad(inputDate.getHours())}:${pad(inputDate.getMinutes())}`;
        };
        form.start_time = formatLocal(d);
        d.setHours(10, 0, 0, 0);
        form.end_time = formatLocal(d);
    }
    
    isDialogOpen.value = true;
};

const openEditDialog = (schedule: Schedule) => {
    isEditing.value = true;
    editingId.value = schedule.id;
    form.title = schedule.title;
    form.description = schedule.description || '';
    form.start_time = schedule.start_time.slice(0, 16);
    form.end_time = schedule.end_time.slice(0, 16);
    form.location = schedule.location || '';
    isDialogOpen.value = true;
};

const submit = () => {
    if (isEditing.value && editingId.value) {
        form.put(scheduleRoutes.update(editingId.value).url, {
            onSuccess: () => {
                isDialogOpen.value = false;
                form.reset();
                Swal.fire({
                    icon: 'success',
                    title: t('schedules.scheduleUpdated'),
                    text: t('schedules.scheduleUpdatedDesc'),
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: t('schedules.updateFailed'),
                    text: t('schedules.updateError'),
                });
            },
        });
    } else {
        form.post(scheduleRoutes.store().url, {
            onSuccess: () => {
                isDialogOpen.value = false;
                form.reset();
                Swal.fire({
                    icon: 'success',
                    title: t('schedules.scheduleCreated'),
                    text: t('schedules.scheduleCreatedDesc'),
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: t('schedules.createFailed'),
                    text: t('schedules.createError'),
                });
            },
        });
    }
};

const deleteSchedule = (id: number) => {
    isDialogOpen.value = false;
    setTimeout(() => {
        Swal.fire({
            title: t('schedules.deleteSchedule'),
            text: t('schedules.deleteScheduleDesc'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6b7280',
            confirmButtonText: t('schedules.yesDelete'),
            cancelButtonText: t('common.cancel'),
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(scheduleRoutes.destroy(id).url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire({
                            icon: 'success',
                            title: t('schedules.scheduleDeleted'),
                            text: t('schedules.scheduleDeletedDesc'),
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    },
                    onError: () => {
                        Swal.fire({
                            icon: 'error',
                            title: t('schedules.deleteFailed'),
                            text: t('schedules.deleteError'),
                        });
                    },
                });
            }
        });
    }, 200);
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('schedules.title'),
        href: scheduleRoutes.index().url,
    },
]);
</script>

<template>
    <Head :title="t('schedules.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ $t('schedules.scheduleList') }}
                    </h2>
                     <div class="flex items-center rounded-md border bg-muted p-1">
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            :class="{'bg-background shadow-sm': viewMode === 'list'}"
                            @click="viewMode = 'list'"
                        >
                            <List class="h-4 w-4" />
                        </Button>
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            :class="{'bg-background shadow-sm': viewMode === 'calendar'}"
                            @click="viewMode = 'calendar'"
                        >
                            <CalendarIcon class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div v-if="viewMode === 'calendar'" class="flex items-center gap-2 mr-2">
                        <Button variant="outline" size="icon" @click="prevMonth">
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <span class="min-w-[120px] text-center font-medium">{{ monthName }}</span>
                        <Button variant="outline" size="icon" @click="nextMonth">
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>

                    <Button @click="openCreateDialog()">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ $t('schedules.addSchedule') }}
                    </Button>
                </div>
            </div>

            <!-- List View -->
            <div v-if="viewMode === 'list'" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="schedule in schedules"
                    :key="schedule.id"
                    class="rounded-lg border bg-card text-card-foreground shadow-sm p-4 flex flex-col gap-2 relative group"
                >
                    <div class="flex justify-between items-start">
                        <h3 class="font-semibold text-lg">{{ schedule.title }}</h3>
                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity absolute top-2 right-2 bg-card p-1 rounded-md shadow-sm border">
                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="openEditDialog(schedule)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="deleteSchedule(schedule.id)">
                                <Trash class="h-4 w-4 text-red-500" />
                            </Button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-muted-foreground" v-if="schedule.location">
                        <MapPin class="h-3 w-3" />
                         {{ schedule.location }}
                    </div>
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Clock class="h-3 w-3" />
                        {{ new Date(schedule.start_time).toLocaleString(locale === 'id' ? 'id-ID' : 'en-US') }} - 
                        {{ new Date(schedule.end_time).toLocaleString(locale === 'id' ? 'id-ID' : 'en-US') }}
                    </div>
                    <p class="text-sm mt-2 whitespace-pre-wrap" v-if="schedule.description">
                        {{ schedule.description }}
                    </p>
                </div>
                
                <div v-if="schedules.length === 0" class="col-span-full text-center text-muted-foreground py-10">
                    {{ $t('schedules.noSchedule') }}
                </div>
            </div>

            <!-- Calendar View -->
            <div v-else class="rounded-md border shadow-sm bg-card text-card-foreground overflow-hidden">
                <!-- Day Headers -->
                <div class="grid grid-cols-7 border-b bg-muted/50">
                    <div 
                        v-for="day in weekDays" 
                        :key="day" 
                        class="p-2 text-center text-sm font-medium text-muted-foreground"
                    >
                        {{ day }}
                    </div>
                </div>
                
                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 auto-rows-[120px]">
                    <div 
                        v-for="(day, index) in calendarDays" 
                        :key="index"
                        class="border-b border-r p-2 relative group hover:bg-muted/20 transition-colors"
                        :class="{'bg-muted/10 text-muted-foreground': !day.isCurrentMonth}"
                        @click="openCreateDialog(day.date)"
                    >
                        <span 
                            class="text-sm rounded-full w-7 h-7 flex items-center justify-center font-medium"
                            :class="{
                                'bg-primary text-primary-foreground': day.isCurrentMonth && day.date.toDateString() === new Date().toDateString(),
                            }"
                        >
                            {{ day.date.getDate() }}
                        </span>

                        <!-- Events -->
                         <div class="mt-1 flex flex-col gap-1 overflow-y-auto max-h-[80px] custom-scrollbar">
                            <div 
                                v-for="event in day.events" 
                                :key="event.id"
                                class="text-xs bg-primary/10 text-primary px-1.5 py-0.5 rounded truncate cursor-pointer hover:bg-primary/20"
                                @click.stop="openEditDialog(event)"
                                :title="event.title"
                            >
                                {{ event.title }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{{ isEditing ? $t('schedules.editSchedule') : $t('schedules.addSchedule') }}</DialogTitle>
                        <DialogDescription>
                            {{ isEditing ? $t('schedules.editScheduleDesc') : $t('schedules.createScheduleDesc') }}
                        </DialogDescription>
                    </DialogHeader>
                    
                    <form @submit.prevent="submit" class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label htmlFor="title">{{ $t('schedules.eventTitle') }}</Label>
                            <Input id="title" v-model="form.title" required />
                            <p v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</p>
                        </div>
                        
                        <div class="grid gap-2">
                            <Label htmlFor="start_time">{{ $t('schedules.startTime') }}</Label>
                            <Input id="start_time" type="datetime-local" v-model="form.start_time" required />
                            <p v-if="form.errors.start_time" class="text-sm text-red-500">{{ form.errors.start_time }}</p>
                        </div>

                        <div class="grid gap-2">
                            <Label htmlFor="end_time">{{ $t('schedules.endTime') }}</Label>
                            <Input id="end_time" type="datetime-local" v-model="form.end_time" required />
                            <p v-if="form.errors.end_time" class="text-sm text-red-500">{{ form.errors.end_time }}</p>
                        </div>

                        <div class="grid gap-2">
                            <Label htmlFor="location">{{ $t('schedules.location') }}</Label>
                            <Input id="location" v-model="form.location" />
                            <p v-if="form.errors.location" class="text-sm text-red-500">{{ form.errors.location }}</p>
                        </div>

                        <div class="grid gap-2">
                            <Label htmlFor="description">{{ $t('schedules.description') }}</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                             <p v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</p>
                        </div>
                    </form>

                    <DialogFooter>
                        <div class="flex justify-between w-full" v-if="isEditing">
                             <Button type="button" variant="destructive" @click="deleteSchedule(editingId!)">
                                {{ $t('schedules.delete') }}
                            </Button>
                            <Button type="submit" @click="submit" :disabled="form.processing">
                                {{ $t('schedules.saveChanges') }}
                            </Button>
                        </div>
                         <Button type="submit" @click="submit" :disabled="form.processing" v-else>
                            {{ $t('schedules.createSchedule') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.1);
    border-radius: 4px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
}
</style>
