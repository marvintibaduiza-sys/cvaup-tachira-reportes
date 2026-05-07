<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

defineProps({
    backups: { type: Array, default: () => [] },
    estadisticas: { type: Object, required: true },
    cronograma: { type: Object, default: () => ({}) },
});

const confirmDelete = ref(null);

const performDelete = () => {
    if (!confirmDelete.value) return;
    router.delete(`/backups/${confirmDelete.value.filename}`, {
        preserveScroll: true,
        onFinish: () => (confirmDelete.value = null),
    });
};

const descargarUrl = (filename) => `/backups/${filename}/descargar`;
</script>

<template>
    <Head title="Historial de Respaldos" />

    <AuthenticatedLayout title="Historial de Respaldos">
        <div class="space-y-4">
            <!-- Header con stats + CTA -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-2 bg-white border border-slate-200 rounded-md px-3 py-1.5 text-sm">
                        <Icon name="backup" :size="16" class="text-cvaup-primary" />
                        <strong class="tabular-nums">{{ estadisticas.total }}</strong> respaldo(s)
                    </span>
                    <span class="inline-flex items-center gap-2 bg-white border border-slate-200 rounded-md px-3 py-1.5 text-sm">
                        <strong class="tabular-nums">{{ estadisticas.total_size_mb }}</strong> MB en disco
                    </span>
                </div>
                <Link
                    href="/backups/generar"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-cvaup-primary text-white text-sm font-semibold rounded-md hover:bg-cvaup-secondary transition-colors"
                >
                    <Icon name="plus" :size="18" />
                    Generar nuevo respaldo
                </Link>
            </div>

            <!-- Banner condicional según entorno -->
            <!-- ENV=production: muestra el cronograma activo con próximas/últimas ejecuciones -->
            <!-- ENV=local/dev: explica que el automático se activa solo en producción -->
            <div v-if="cronograma.automatico_activo" class="bg-cvaup-primary/5 border border-cvaup-primary/20 rounded-md p-4 text-sm">
                <div class="flex items-start gap-3">
                    <Icon name="calendar" :size="20" class="text-cvaup-primary mt-0.5 shrink-0" />
                    <div class="flex-1">
                        <p class="font-semibold text-cvaup-primary mb-2">Cronograma automático activo (producción)</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                            <div>
                                <div class="text-slate-500">Próximo backup automático</div>
                                <div class="font-semibold text-slate-800 tabular-nums">{{ cronograma.proxima_ejecucion }}</div>
                                <div class="text-[11px] text-slate-500">{{ cronograma.proxima_ejecucion_relativa }}</div>
                            </div>
                            <div>
                                <div class="text-slate-500">Último backup registrado</div>
                                <div class="font-semibold text-slate-800 tabular-nums">{{ cronograma.ultimo_backup ?? '—' }}</div>
                                <div class="text-[11px]">
                                    <span v-if="cronograma.ultimo_fue_automatico" class="text-cvaup-primary">✓ Generado automáticamente</span>
                                    <span v-else-if="cronograma.ultimo_backup" class="text-slate-500">Generado manualmente</span>
                                    <span v-else class="text-slate-400">Aún no hay respaldos</span>
                                </div>
                            </div>
                            <div>
                                <div class="text-slate-500">Política de retención</div>
                                <div class="font-semibold text-slate-800">Últimos {{ estadisticas.max_retenidos }} respaldos</div>
                                <div class="text-[11px] text-slate-500">El más antiguo se elimina al exceder</div>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-cvaup-primary/10 text-[11px] text-slate-500">
                            <strong>Cronograma:</strong>
                            {{ cronograma.horario_limpieza }} (limpieza) ·
                            {{ cronograma.horario_diario }} (backup diario) ·
                            {{ cronograma.horario_monitor }} (verificación de salud)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modo desarrollo: explica que el cron NO está corriendo aquí -->
            <div v-else class="bg-amber-50 border border-amber-200 rounded-md p-4 text-sm">
                <div class="flex items-start gap-3">
                    <Icon name="warning" :size="20" class="text-amber-700 mt-0.5 shrink-0" />
                    <div class="flex-1">
                        <p class="font-semibold text-amber-900 mb-2">Modo desarrollo — backup automático NO activo</p>
                        <p class="text-xs text-amber-900 leading-relaxed">
                            Estás en entorno <code class="bg-amber-100 px-1 rounded">{{ cronograma.entorno }}</code>.
                            El cronograma automático
                            (<strong>{{ cronograma.horario_limpieza }} limpieza · {{ cronograma.horario_diario }} backup diario · {{ cronograma.horario_monitor }} monitor</strong>)
                            está <strong>definido en código</strong> pero <strong>NO se está ejecutando</strong> en tu computadora local.
                        </p>
                        <p class="text-xs text-amber-900 mt-2 leading-relaxed">
                            <strong>Esto es correcto.</strong> El backup automático se activa <strong>únicamente en el servidor de producción</strong>
                            de la institución (que está encendido 24/7). Tu PC normalmente está apagada a las 2:30 AM,
                            así que correr el cron acá sería inútil.
                        </p>
                        <p class="text-xs text-amber-900 mt-2 leading-relaxed">
                            <strong>En desarrollo:</strong> usa el botón <em>"Generar nuevo respaldo"</em> arriba para crear backups manuales cuando los necesites.
                        </p>
                        <p class="text-xs text-amber-900 mt-2 leading-relaxed">
                            <strong>Para producción:</strong> el técnico de TI de la institución debe seguir el playbook en
                            <code class="bg-amber-100 px-1 rounded">{{ cronograma.docs_despliegue }}</code>
                            para activar el cron + configurar destino remoto (Drive/SFTP/NAS) + SMTP de alertas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Banner secundario: detalles técnicos del backup -->
            <div class="bg-blue-50 border border-blue-200 text-blue-900 rounded-md p-3 text-xs flex items-start gap-2">
                <Icon name="warning" :size="16" class="text-blue-600 mt-0.5 shrink-0" />
                <div>
                    Cada respaldo incluye: dump de la base de datos <code class="bg-blue-100 px-1 rounded">cvaup_tachira</code>
                    + carpeta <code class="bg-blue-100 px-1 rounded">storage/app/public/fotos</code> + archivo <code class="bg-blue-100 px-1 rounded">.env</code>.
                    Almacenados en <code class="bg-blue-100 px-1 rounded">{{ estadisticas.directorio }}</code>.
                </div>
            </div>

            <!-- Tabla -->
            <Card padding="none">
                <EmptyState v-if="backups.length === 0" icon="backup">
                    <div class="space-y-3">
                        <p>Aún no se ha generado ningún respaldo.</p>
                        <Link href="/backups/generar" class="inline-block text-sm text-cvaup-primary hover:underline">
                            Genera el primero →
                        </Link>
                    </div>
                </EmptyState>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3 font-semibold">Fecha de generación</th>
                                <th class="px-4 py-3 font-semibold">Nombre del archivo</th>
                                <th class="px-4 py-3 font-semibold text-right">Tamaño</th>
                                <th class="px-4 py-3 font-semibold text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="b in backups" :key="b.filename" class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 text-slate-700 whitespace-nowrap">
                                    {{ b.created_at }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 font-mono text-xs">
                                    {{ b.filename }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                    <span v-if="b.size_mb >= 1">{{ b.size_mb }} MB</span>
                                    <span v-else>{{ b.size_kb }} KB</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a
                                            :href="descargarUrl(b.filename)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-cvaup-primary text-white rounded hover:bg-cvaup-secondary transition-colors"
                                            title="Descargar este respaldo"
                                        >
                                            <Icon name="download" :size="14" />
                                            Descargar
                                        </a>
                                        <button
                                            type="button"
                                            @click="confirmDelete = b"
                                            class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                                            title="Eliminar este respaldo"
                                        >
                                            <Icon name="trash" :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <ConfirmDialog
            :show="confirmDelete !== null"
            title="Eliminar respaldo"
            :message="`¿Estás seguro de eliminar el respaldo '${confirmDelete?.filename}' del ${confirmDelete?.created_at}? Esta acción NO se puede deshacer.`"
            confirm-label="Sí, eliminar"
            variant="danger"
            @confirm="performDelete"
            @cancel="confirmDelete = null"
        />
    </AuthenticatedLayout>
</template>
