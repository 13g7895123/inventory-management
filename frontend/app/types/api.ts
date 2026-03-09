// app/types/api.ts
// 統一 API 回應型別定義

export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
  errors: Record<string, string[]> | null
}

export interface PaginatedResponse<T = unknown> {
  success: boolean
  message: string
  data: T[]
  errors: Record<string, string[]> | null
  pagination: Pagination
}

export interface Pagination {
  current_page: number
  per_page: number
  total: number
  total_pages: number
}

// ── Domain Types ──────────────────────────────────────────────────

export interface Item {
  id: number
  category_id: number
  unit_id: number
  code: string
  name: string
  description: string | null
  reorder_point: number
  safety_stock: number
  lead_time_days: number
  image_path: string | null
  is_active: boolean
  category_name?: string
  unit_name?: string
  created_at: string
  updated_at: string
}

export interface ItemSku {
  id: number
  item_id: number
  sku_code: string
  barcode: string | null
  attributes: Record<string, string>
  item_name?: string
}

export interface Inventory {
  id: number
  sku_id: number
  warehouse_id: number
  on_hand_qty: number
  reserved_qty: number
  on_order_qty: number
  avg_cost: number
  available_qty: number  // 計算欄位：on_hand - reserved
  sku_code?: string
  item_name?: string
  warehouse_name?: string
}

export interface PurchaseOrder {
  id: number
  po_number: string
  supplier_id: number
  status: 'DRAFT' | 'PENDING' | 'APPROVED' | 'PARTIAL' | 'RECEIVED' | 'CANCELLED'
  expected_arrival_date: string
  total_amount: number
  created_by: number
  approved_by: number | null
  supplier_name?: string
  line_items?: PurchaseOrderLine[]
}

export interface PurchaseOrderLine {
  id: number
  po_id: number
  sku_id: number
  ordered_qty: number
  received_qty: number
  unit_price: number
  sku_code?: string
  item_name?: string
}

export interface SalesOrder {
  id: number
  so_number: string
  customer_id: number
  status: 'DRAFT' | 'CONFIRMED' | 'PARTIAL' | 'SHIPPED' | 'CANCELLED'
  requested_ship_date: string
  total_amount: number
  created_by: number
  customer_name?: string
  line_items?: SalesOrderLine[]
}

export interface SalesOrderLine {
  id: number
  so_id: number
  sku_id: number
  warehouse_id: number
  ordered_qty: number
  shipped_qty: number
  unit_price: number
  sku_code?: string
  item_name?: string
}

// ── Auth ──────────────────────────────────────────────────────────

export interface LoginPayload {
  username: string
  password: string
}

export interface AuthTokens {
  access_token: string
  refresh_token: string
  expires_in: number
  token_type: 'Bearer'
}

export interface AuthUser {
  id: number
  username: string
  name: string
  role: string
  permissions?: string[]
}
