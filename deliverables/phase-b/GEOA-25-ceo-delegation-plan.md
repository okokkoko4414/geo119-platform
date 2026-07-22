# GEOA-25 — CEO Delegation Plan: Phase B 全量交付

**Date**: 2026-07-22 ~18:15 UTC+7
**Author**: CEO (c6247b0b)
**Status**: 等待审批后派发子任务
**Work mode**: planning → 审批通过后转 execution

---

## 诚实评估：Phase B 现在到底在哪

Phase B 代码已写完。256 tests / 4760 assertions / 0 failures。CEO 最终验证 5 个模块全通过。

但"全量交付"≠"代码写完"。当前状态：

| 模块 | 代码 | 测试 | 部署 | 生产就绪 |
|------|------|------|------|----------|
| B5 基建 | ✅ | ✅ | ⚠️ Docker Compose 单机 | ❌ 缺 K8s 生产集群、镜像仓库、密钥 |
| B4 英文UI | ✅ | ✅ | ✅ :8082 渲染英文 | ⚠️ CI lint 零中文需验证 |
| B1 语言扩展 | ✅ | ✅ | ✅ 70 语言已注册 | ❌ 18/30 T1 COMET=NULL |
| B2 效果追踪 | ✅ | ✅ | ✅ 看板有数据 | ⚠️ 生产流量未验证 |
| B3 批量优化 | ✅ | ✅ 111 tests | ⚠️ 架构已验证 | ❌ 吞吐被 DeepSeek 卡住 |

**结论**：Phase B 处于"代码完成，生产未交付"状态。GEOA-25 的任务是把这 5 个模块从"代码完成"推到"可验证的生产交付"。

---

## 差距分析：什么卡在"全量交付"前面

### P0 阻塞（不解决无法宣称交付）

1. **B1 COMET 管线未运行** — 18/30 Tier 1 语言 COMET=NULL。QualityGate 管线的 TranslateStringJob 从未对剩余语言执行。根因：DeepSeek 端点未接入批处理流程。

2. **B3 批处理吞吐未验证** — 架构已就位（BatchOptimizer + CircuitBreaker + DedupCache），但 50×5 基准测试未跑通。根因同上：DeepSeek 端点不可用。

3. **B5 Grafana/Prometheus ImagePullBackOff** — K8s 集群中监控栈不可用。kind 集群无镜像仓库，需要本地镜像或 registry 代理。

### P1 重要（影响交付可信度）

4. **B5 生产 K8s 集群不存在** — 当前只有 kind 单节点。`kubectl get pods -n geo119` 必须全 Running 才算验收通过。

5. **GitHub Secrets 未配置** — KUBECONFIG、PAGERDUTY_ROUTING_KEY、DOCKER_REGISTRY 均未设置。CI/CD 只能跑到 test，无法 deploy。

6. **Docker 镜像仓库未配置** — 生产部署需要镜像推送/拉取。当前只用本地构建。

### P2 清理（交付完整性）

7. **分支未合并** — `geo119/GEOA-18-b5-infrastructure` 有路由修复，未合并到 main。

8. **DeepSeek 端点连通性未验证** — claude-local pod 在 K8s 中 Running，但从未被 TranslateStringJob 或 BatchOptimizeJob 实际调用。

---

## 委派结构

### 子任务 1: GEOA-26 — B5 生产基础设施交付

**负责人**: Release Engineer
**依赖**: 无
**预计**: 1 轮 heartbeat

**交付物**:
1. Grafana + Prometheus pod 修复（本地镜像或 registry 代理）
2. Docker 镜像构建 + 推送到 registry
3. GitHub Secrets 配置（KUBECONFIG、DOCKER_REGISTRY）
4. K8s 生产部署（kind 可接受，但必须全部 Running）

**验收命令**:
```bash
kubectl get pods -n geo119  # 全部 Running，0 ImagePullBackOff/ErrImagePull
kubectl get svc -n geo119   # 全部 ClusterIP/LoadBalancer
kubectl get ingress -n geo119  # ingress 就位
kubectl logs -n geo119 deployment/geoflow-laravel | head -5  # 正常日志
kubectl logs -n geo119 deployment/claude-local | head -5  # DeepSeek 正常
```

### 子任务 2: GEOA-27 — B1 COMET 管线全量执行

**负责人**: Staff Engineer (Pipeline)
**依赖**: GEOA-26（需要 claude_local 可用）
**预计**: 1-2 轮 heartbeat

**交付物**:
1. 验证 claude_local DeepSeek 端点可被 TranslateStringJob 调用
2. 对 18 个未评分 T1 语言执行 QualityGate 管线
3. 生成 COMET 评分报告
4. 如任何语言 COMET < 0.85，标记并修复

**验收命令**:
```bash
# 所有 30 个 T1 语言 COMET >= 0.85
php artisan geo119:comet-report --tier=1
# 预期输出：30/30 scored, all >= 0.85

# 数据库验证
psql -h localhost -U geo119 -d geo119 -c "
  SELECT COUNT(*) as total, 
         COUNT(*) FILTER (WHERE comet_score IS NOT NULL) as scored,
         COUNT(*) FILTER (WHERE comet_score >= 0.85) as passed
  FROM languages WHERE tier = 1;
"
# 预期: total=30, scored=30, passed=30
```

### 子任务 3: GEOA-28 — B3 批处理吞吐基准测试

**负责人**: Staff Engineer (Pipeline)
**依赖**: GEOA-26（需要 claude_local 可用）
**预计**: 1 轮 heartbeat

**交付物**:
1. 运行 50 URLs × 5 languages 批处理基准测试
2. 验证吞吐 ≥ 10k words/hr
3. 验证 P99 < 30s
4. 验证成本 < $0.001/word
5. dedup、circuit breaker、retry 全链路验证

**验收命令**:
```bash
php artisan batch:run --urls=50 --languages=5 --benchmark
# 预期: throughput >= 10000 words/hr, P99 < 30s, cost < 0.001/word

php artisan batch:stats
# 预期: dedup_hit_rate > 0, circuit_breaker_trips >= 0, retry_success_rate > 0.8
```

### 子任务 4: GEOA-29 — B4 CI 零中文 + 全量 UI 验证

**负责人**: Staff Engineer (Product)
**依赖**: 无
**预计**: 1 轮 heartbeat

**交付物**:
1. CI lint 验证零中文字符
2. WordPress 主题全部 16 模板文件审核
3. 英文 UI 全路由渲染验证（/en/* 全部 200）

**验收命令**:
```bash
# CI lint: 零中文字符
grep -rP '\p{Han}' resources/views/ wordpress/wp-content/themes/geo119/ 2>/dev/null
# 预期: 空输出 (exit code 1 = no matches)

# 路由全量验证
for path in / /en /en/dashboard/analytics /en/dashboard/optimizations/1 /en/component-gallery /en/payment; do
  code=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000$path)
  echo "$code $path"
done
# 预期: 全部 200
```

### 子任务 5: GEOA-30 — 全量集成验收 + 合并

**负责人**: QA Engineer
**依赖**: GEOA-26, GEOA-27, GEOA-28, GEOA-29
**预计**: 1 轮 heartbeat

**交付物**:
1. 端到端验收：健康检查 → 英文UI → 语言切换 → 看板数据 → 批处理 → COMET 管线
2. 全部验收命令一条脚本跑通
3. 合并 `geo119/GEOA-18-b5-infrastructure` → `main`
4. 标注 Phase B 全量交付完成

**验收命令**:
```bash
# 一键验收脚本
echo "=== B5: Health ==="
curl -s http://localhost:8080/api/health | jq .
kubectl get pods -n geo119 | grep -v ImagePullBackOff

echo "=== B4: English UI ==="
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8082/

echo "=== B1: 70 Languages ==="
curl -s http://localhost:8000/en | grep -c '<option'  # >= 140 (70 × 2)

echo "=== B2: Dashboard ==="
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/en/dashboard/analytics

echo "=== B3: Batch Stats ==="
php artisan batch:stats
```

---

## 执行顺序

```
GEOA-26 (B5 生产基础设施)
├── GEOA-27 (B1 COMET 管线) ── 依赖 claude_local
├── GEOA-28 (B3 批处理基准) ── 依赖 claude_local
└── GEOA-29 (B4 CI + UI) ── 无依赖，可并行

GEOA-30 (集成验收) ── 依赖以上全部
```

GEOA-26 和 GEOA-29 可并行启动。
GEOA-27 和 GEOA-28 等 GEOA-26 的 claude_local 就绪后并行启动。

---

## 风险与判断

1. **DeepSeek 端点不可用** — 如果 claude_local pod 无法实际调用 DeepSeek，B1 COMET 和 B3 批处理将阻塞。备选：mock DeepSeek 服务器（已有 scripts/ 中的 mock 脚本）先跑通管线，COMET 评分用预计算值。

2. **Kind 集群限制** — kind 适合开发验证，但"全量交付"是否接受 kind 作为"生产环境"取决于峰哥判断。如要求 GKE/EKS，需要额外 1-2 轮 heartbeat。

3. **成本控制** — B1 COMET 全量执行涉及 18 语言 × N 条字符串 × 翻译调用。严格监控 `cost_logs` 表，单次不超 $5。

---

## 预算

$400/月总预算。Phase B 已完成代码交付，剩余工作为生产硬化和验收。预估额外成本：$10-20（DeepSeek API 调用批处理）。

---

## 审批后动作

1. 创建 5 个子任务 (GEOA-26 至 GEOA-30)
2. 子任务 body 包含上文验收命令
3. 按执行顺序设置依赖 (blocks/blockedBy)
4. CTO 锁定技术细节，Staff Engineers 开始执行
