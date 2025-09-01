import { ref } from 'vue'
import api from '@/plugins/axios'

export function useExtraQuota() {
  const loading = ref(false)
  const error   = ref(null)

  async function fetchUserPortfolio({ unit, fiscalYear }) {
    loading.value = true; error.value = null
    try {
      const { data } = await api.get('/api/extra/portfolio', { params: { unit, fiscal_year: fiscalYear } })
      return {
        title: data?.title ?? 'Zusatzquoten',
        target: Number(data?.target ?? 0),
        achieved: Number(data?.achieved ?? 0),
        items: Array.isArray(data?.items) ? data.items : [],
        mix: data?.mix ?? null
      }
    } catch (e) { error.value = e; return { title:'Zusatzquoten', target:0, achieved:0, items:[], mix:null } }
    finally { loading.value = false }
  }

  async function fetchPcPortfolio({ code, unit, fiscalYear }) {
    loading.value = true; error.value = null
    try {
      const { data } = await api.get(`/api/profit-centers/${code}/extra-portfolio`, { params: { unit, fiscal_year: fiscalYear } })
      return { allocated:Number(data?.allocated??0), won:Number(data?.won??0), lost:Number(data?.lost??0), open:Number(data?.open??0) }
    } catch (e) { error.value = e; return { allocated:0, won:0, lost:0, open:0 } }
    finally { loading.value = false }
  }

  return { loading, error, fetchUserPortfolio, fetchPcPortfolio }
}
