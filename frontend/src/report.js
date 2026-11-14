import { jsPDF } from 'jspdf'
import { applyPlugin } from 'jspdf-autotable'

applyPlugin(jsPDF) // fiable en ESM comme en CommonJS, contrairement à l'export par défaut

const INK = [28, 42, 74]
const SOFT = [90, 96, 107]
const LINE = [220, 223, 229]
const TZ = 'Europe/Paris'

// Les polices standard du PDF ne connaissent pas le signe moins typographique : on garde « - »
const n1 = (v) => (v === null || v === undefined || v === '' ? '' : Number(v).toFixed(1).replace('.', ','))
const dt = (iso) => new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', timeZone: TZ })
const d = (ymd) => (ymd ? ymd.slice(8, 10) + '/' + ymd.slice(5, 7) + '/' + ymd.slice(0, 4) : '')
const visa = (name = '') => name.trim().slice(0, 2).toUpperCase()

/** Rapport HACCP de contrôle sanitaire (cuisine, salle, bar) au format de la charte Rondo. */
export function haccpReport(data) {
  const doc = new jsPDF({ unit: 'mm', format: 'a4' })
  const period = `du ${d(data.from)} au ${d(data.to)}`
  const exported = new Date(data.exportedAt).toLocaleString('fr-FR', { timeZone: TZ, dateStyle: 'short', timeStyle: 'short' })

  const drawn = new Set() // une seule fois par page, même si plusieurs tableaux s'y trouvent
  let space = ''
  const header = () => {
    const page = doc.getCurrentPageInfo().pageNumber
    if (drawn.has(page)) return
    drawn.add(page)
    // le symbole : un « o » posé sur sa ligne
    doc.setDrawColor(...INK).setLineWidth(0.9).circle(16.8, 15.4, 2.4)
    doc.setFillColor(...INK).roundedRect(13.8, 19.2, 6, 0.7, 0.35, 0.35, 'F')
    doc.setFont('helvetica', 'bold').setFontSize(13).setTextColor(...INK).text('rondo', 21.5, 18)
    doc.setFont('helvetica', 'normal').setFontSize(9).setTextColor(...SOFT).text(`${data.restaurant} · Registre ${space} · ${period}`, 196, 18, { align: 'right' })
    doc.setDrawColor(...LINE).setLineWidth(0.3).line(14, 23, 196, 23)
  }
  const table = (title, head, body, opts = {}) => {
    let y = doc.lastAutoTable.finalY + 10
    if (y > 260) { doc.addPage(); header(); y = 32 }
    doc.setFont('helvetica', 'bold').setFontSize(10.5).setTextColor(...INK).text(title, 14, y)
    doc.autoTable({
      head: [head], body: body.length ? body : [[{ content: 'Aucune entrée sur la période.', colSpan: head.length, styles: { textColor: SOFT } }]],
      startY: y + 3,
      margin: { top: 30, left: 14, right: 14 },
      styles: { font: 'helvetica', fontSize: 8.5, cellPadding: 1.8, textColor: [35, 39, 47], lineColor: LINE, lineWidth: { bottom: 0.2 } },
      headStyles: { fillColor: [247, 248, 250], textColor: INK, fontStyle: 'bold' },
      didDrawPage: header,
      ...opts,
    })
  }

  data.spaces.forEach((sp, i) => {
    if (i > 0) doc.addPage()
    space = sp.label
    header()
    doc.setFont('helvetica', 'bold').setFontSize(15).setTextColor(...INK).text(sp.label, 14, 33)
    doc.lastAutoTable = { finalY: 30 }

    table('Relevés de températures', ['N°', 'Date', 'Point de contrôle', '°C', 'Bornes', 'État', 'Action corrective', 'Visa'],
      sp.temps.map((e) => [String(e.n).padStart(4, '0'), dt(e.ts), e.equipmentName, n1(e.value), `${n1(e.min)} à ${n1(e.max)}`, e.ok ? 'Conforme' : 'HORS BORNES', e.comment || '', visa(e.staff)]),
      { columnStyles: { 0: { cellWidth: 11 }, 3: { halign: 'right' }, 5: { fontStyle: 'bold' } } })

    table('Traçabilité', ['N°', 'Date', 'Type', 'Produit', 'DLC', 'Lot', 'Fournisseur', 'Retiré', 'Visa'],
      sp.traces.map((e) => [String(e.n).padStart(4, '0'), dt(e.ts), { dlc: 'DLC', lot: 'Lot', reception: 'Réception' }[e.type] || e.type, e.product, d(e.date), e.lot || '', e.supplier || '', e.removedAt ? dt(e.removedAt) : '', visa(e.staff)]),
      { columnStyles: { 0: { cellWidth: 11 } } })

    table('Nettoyage et contrôles du jour', ['Jour', 'Tâches faites', 'Taux'],
      sp.tasks.map((t) => [d(t.date), `${t.done} / ${t.total}`, t.total ? `${Math.round((100 * t.done) / t.total)} %` : '']))
  })

  const pages = doc.getNumberOfPages()
  for (let p = 1; p <= pages; p++) {
    doc.setPage(p)
    doc.setFont('helvetica', 'normal').setFontSize(8).setTextColor(...SOFT)
    doc.text(`Page ${p} / ${pages}`, 14, 289)
    doc.text(`Exporté le ${exported}`, 196, 289, { align: 'right' })
  }
  doc.save(`rondo-haccp-${data.from}-${data.to}.pdf`)
}
