import { defineStore } from 'pinia'
import axios from 'axios'

function fromBackendWidget(w){
  // backend: {x,y,w,h,i, props:{type,title,kpiId,...}}
  const flat = { x:w.x, y:w.y, w:w.w, h:w.h, i:String(w.i) }
  return { ...flat, ...(w.props || {}) }
}
function toBackendWidget(w){
  const { x,y,w:W,h,i, sort, type, title, kpiId, ...rest } = w
  return { x,y,w:W,h,i:String(i), sort: sort||0, props: { type, title, kpiId, ...rest } }
}

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    dashboard: null,
    layout: [],          // [{x,y,w,h,i,type,title?,kpiId?...}]
    background: 'white', // 'white'|'black'|'bg-1'|'bg-2'|'bg-3'
    loading: false,
    error: null
  }),
  actions: {
    async loadDefault(){
      this.loading = true; this.error = null
      try{
        const { data } = await axios.get('/api/dashboards/default')
        this.dashboard = data
        this.background = data.background || 'white'
        this.layout = (data.widgets || []).map(fromBackendWidget)
      }catch(e){
        this.error = e?.response?.data?.message || 'Error cargando dashboard'
      }finally{
        this.loading = false
      }
    },
    async saveAll({ background, widgets }){
      if (!this.dashboard) throw new Error('No dashboard loaded')
      const payload = {
        background,
        widgets: widgets.map(toBackendWidget)
      }
      const { data } = await axios.put(`/api/dashboards/${this.dashboard.id}`, payload)
      // refrescamos
      this.dashboard = data
      this.background = data.background || background
      this.layout = (data.widgets || []).map(fromBackendWidget)
    }
  }
})