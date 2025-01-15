# Laravel Anthropic Middleware Sequence Diagrams

This document provides sequence diagrams illustrating the flow and interactions within the middleware stack.

## Basic Request Flow

```mermaid
sequenceDiagram
    participant C as Client
    participant R as Router
    participant HE as HandleAnthropicErrors
    participant VC as ValidateAnthropicConfig
    participant RL as RateLimitRequests
    participant LR as LogRequests
    participant CR as CacheResponses
    participant TR as TransformResponse
    participant H as Handler
    participant A as Anthropic API

    C->>R: HTTP Request
    R->>HE: Forward Request
    
    HE->>VC: Try/Catch Block Starts
    VC->>RL: Validate Config
    RL->>LR: Check Rate Limit
    
    alt Cache Hit
        LR->>CR: Log Request
        CR->>TR: Return Cached Response
        TR->>HE: Transform Response
        HE->>C: Return Response
    else Cache Miss
        LR->>CR: Log Request
        CR->>TR: Forward Request
        TR->>H: Forward Request
        H->>A: API Request
        A->>H: API Response
        H->>TR: Return Response
        TR->>CR: Transform Response
        CR->>LR: Cache Response
        LR->>RL: Log Response
        RL->>VC: Update Rate Limit
        VC->>HE: Forward Response
        HE->>C: Return Response
    end
```

## Error Handling Flow

```mermaid
sequenceDiagram
    participant C as Client
    participant HE as HandleAnthropicErrors
    participant VC as ValidateAnthropicConfig
    participant RL as RateLimitRequests
    participant LR as LogRequests
    participant TR as TransformResponse

    C->>HE: HTTP Request
    
    alt Configuration Error
        HE->>VC: Forward Request
        VC--xHE: Throw ConfigError
        HE->>LR: Log Error
        HE->>TR: Transform Error
        HE->>C: Return Error Response
    else Rate Limit Error
        HE->>VC: Forward Request
        VC->>RL: Forward Request
        RL--xHE: Throw RateLimitError
        HE->>LR: Log Error
        HE->>TR: Transform Error
        HE->>C: Return Error Response
    else API Error
        HE->>VC: Forward Request
        VC->>RL: Forward Request
        RL->>LR: Forward Request
        LR--xHE: Throw APIError
        HE->>LR: Log Error
        HE->>TR: Transform Error
        HE->>C: Return Error Response
    end
```

## Caching Flow

```mermaid
sequenceDiagram
    participant C as Client
    participant CR as CacheResponses
    participant H as Handler
    participant A as Anthropic API
    participant Cache as Cache Store

    C->>CR: HTTP Request
    
    CR->>Cache: Check Cache
    
    alt Cache Hit
        Cache->>CR: Return Cached Data
        CR->>C: Return Cached Response
    else Cache Miss
        Cache-->>CR: Cache Miss
        CR->>H: Forward Request
        H->>A: API Request
        A->>H: API Response
        H->>CR: Return Response
        CR->>Cache: Store Response
        CR->>C: Return Response
    end
```

## Rate Limiting Flow

```mermaid
sequenceDiagram
    participant C as Client
    participant RL as RateLimitRequests
    participant Redis as Rate Limiter
    participant H as Handler
    participant A as Anthropic API

    C->>RL: HTTP Request
    
    RL->>Redis: Check Rate Limit
    
    alt Limit Exceeded
        Redis-->>RL: Limit Exceeded
        RL->>C: Return 429 Response
    else Limit Available
        Redis->>RL: Decrement Limit
        RL->>H: Forward Request
        H->>A: API Request
        A->>H: API Response
        H->>RL: Return Response
        RL->>C: Return Response with Headers
    end
```

## Response Transformation Flow

```mermaid
sequenceDiagram
    participant H as Handler
    participant TR as TransformResponse
    participant M as Metadata Service
    participant C as Client

    H->>TR: Raw Response
    
    TR->>M: Get Request Metadata
    M->>TR: Return Metadata
    
    alt JSON Response
        TR->>TR: Transform JSON
        TR->>TR: Add Metadata
        TR->>C: Return Transformed Response
    else Binary Response
        TR->>C: Return Original Response
    end
```

## Logging Flow

```mermaid
sequenceDiagram
    participant C as Client
    participant LR as LogRequests
    participant H as Handler
    participant L as Logger
    participant M as Metrics

    C->>LR: HTTP Request
    
    LR->>L: Log Request
    LR->>M: Start Timer
    LR->>H: Forward Request
    H->>LR: Return Response
    LR->>M: Stop Timer
    LR->>L: Log Response
    LR->>L: Log Metrics
    LR->>C: Return Response
```

## Event Flow

```mermaid
sequenceDiagram
    participant C as Client
    participant MW as Middleware Stack
    participant E as Event Dispatcher
    participant L as Listeners
    participant M as Monitoring

    C->>MW: HTTP Request
    
    MW->>E: Dispatch RequestProcessed
    E->>L: Notify Listeners
    L->>M: Update Metrics
    
    alt Error Occurs
        MW->>E: Dispatch ErrorOccurred
        E->>L: Notify Listeners
        L->>M: Log Error
    else Cache Hit
        MW->>E: Dispatch CacheHit
        E->>L: Notify Listeners
        L->>M: Update Cache Stats
    else Rate Limited
        MW->>E: Dispatch RateLimited
        E->>L: Notify Listeners
        L->>M: Update Rate Metrics
    end
    
    MW->>C: Return Response
```

These sequence diagrams illustrate:
- Complete request lifecycle
- Error handling paths
- Caching mechanisms
- Rate limiting logic
- Response transformation
- Logging and monitoring
- Event dispatching

The diagrams help visualize:
- Component interactions
- Decision points
- Data flow
- Error paths
- Asynchronous operations
- System boundaries
