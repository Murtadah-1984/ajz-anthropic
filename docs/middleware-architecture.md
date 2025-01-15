# Laravel Anthropic Middleware Architecture

This document provides architectural diagrams illustrating the structure and relationships of the middleware components.

## Class Diagram

```mermaid
classDiagram
    class Middleware {
        <<interface>>
        +handle(Request, Closure): Response
    }

    class HandleAnthropicErrors {
        -shouldIncludeDebugInfo(): bool
        -sanitizeContext(array): array
        +handle(Request, Closure): Response
        +handleException(Throwable): JsonResponse
        #formatError(Throwable): array
    }

    class ValidateAnthropicConfig {
        -requiredConfig: array
        +handle(Request, Closure): Response
        #validateConfig(): void
        #getRequiredConfig(): array
    }

    class RateLimitAnthropicRequests {
        -limiter: RateLimiter
        -config: array
        +handle(Request, Closure): Response
        #resolveRequestSignature(Request): string
        #handleRateLimitExceeded(int): JsonResponse
        #addRateLimitHeaders(Response, RateLimiter): void
    }

    class LogAnthropicRequests {
        -logger: Logger
        -config: array
        +handle(Request, Closure): Response
        #logRequest(Request): void
        #logResponse(Response): void
        #formatRequestData(Request): array
        #formatResponseData(Response): array
    }

    class CacheAnthropicResponses {
        -cache: Cache
        -config: array
        +handle(Request, Closure): Response
        #getCacheKey(Request): string
        #shouldCache(Request, Response): bool
        #cacheResponse(string, Response): void
        #getCachedResponse(string): ?Response
    }

    class TransformAnthropicResponse {
        -config: array
        +handle(Request, Closure): Response
        #shouldTransform(Response): bool
        #transformResponse(Response, float): array
        #getResponseData(Response): array
        #getResponseMessage(array, bool): ?string
        #getErrorDetails(array): ?array
        #isBinaryResponse(Response): bool
        #getPaginationMetadata(array): ?array
    }

    Middleware <|.. HandleAnthropicErrors
    Middleware <|.. ValidateAnthropicConfig
    Middleware <|.. RateLimitAnthropicRequests
    Middleware <|.. LogAnthropicRequests
    Middleware <|.. CacheAnthropicResponses
    Middleware <|.. TransformAnthropicResponse
```

## Component Diagram

```mermaid
graph TB
    subgraph Client Layer
        C[Client]
    end

    subgraph Middleware Stack
        HE[HandleAnthropicErrors]
        VC[ValidateAnthropicConfig]
        RL[RateLimitRequests]
        LR[LogRequests]
        CR[CacheResponses]
        TR[TransformResponse]
    end

    subgraph Services
        Logger[Logger Service]
        Cache[Cache Service]
        Redis[Rate Limiter]
        Config[Config Service]
        Events[Event Dispatcher]
    end

    subgraph External
        API[Anthropic API]
    end

    C --> HE
    HE --> VC
    VC --> RL
    RL --> LR
    LR --> CR
    CR --> TR
    TR --> API

    HE -.-> Logger
    HE -.-> Events
    VC -.-> Config
    RL -.-> Redis
    LR -.-> Logger
    CR -.-> Cache
    TR -.-> Events
```

## Package Structure

```mermaid
graph TB
    subgraph Laravel Anthropic Package
        subgraph Http
            subgraph Middleware
                M1[HandleAnthropicErrors]
                M2[ValidateAnthropicConfig]
                M3[RateLimitRequests]
                M4[LogRequests]
                M5[CacheResponses]
                M6[TransformResponse]
            end
        end

        subgraph Services
            S1[AnthropicService]
            S2[ConfigService]
            S3[CacheService]
            S4[LogService]
        end

        subgraph Events
            E1[RequestProcessed]
            E2[RateLimitExceeded]
            E3[CacheHit]
            E4[ErrorOccurred]
        end

        subgraph Exceptions
            Ex1[AnthropicException]
            Ex2[RateLimitException]
            Ex3[ConfigException]
            Ex4[CacheException]
        end

        subgraph Config
            C1[anthropic.php]
        end
    end
```

## Data Flow Diagram

```mermaid
graph LR
    subgraph Input
        Request[HTTP Request]
    end

    subgraph Processing
        subgraph Validation
            Config[Config Validation]
            Rate[Rate Limiting]
        end

        subgraph Caching
            Check[Cache Check]
            Store[Cache Store]
        end

        subgraph Logging
            ReqLog[Request Logging]
            ResLog[Response Logging]
        end

        subgraph Transform
            Format[Response Format]
            Meta[Add Metadata]
        end
    end

    subgraph Output
        Response[HTTP Response]
    end

    Request --> Config
    Config --> Rate
    Rate --> Check
    Check --> |Cache Miss| API[Anthropic API]
    API --> Store
    Store --> Format
    Format --> Meta
    Meta --> Response
    Check --> |Cache Hit| Format
```

## Event Flow Diagram

```mermaid
graph TB
    subgraph Events
        E1[RequestProcessed]
        E2[RateLimitExceeded]
        E3[CacheHit]
        E4[ErrorOccurred]
    end

    subgraph Listeners
        L1[MetricsListener]
        L2[LogListener]
        L3[NotificationListener]
        L4[MonitoringListener]
    end

    subgraph Actions
        A1[Update Metrics]
        A2[Log Event]
        A3[Send Notification]
        A4[Update Monitoring]
    end

    E1 --> L1 & L2
    E2 --> L1 & L2 & L3
    E3 --> L1 & L2
    E4 --> L1 & L2 & L3 & L4

    L1 --> A1
    L2 --> A2
    L3 --> A3
    L4 --> A4
```

## Configuration Structure

```mermaid
graph TB
    subgraph Config File
        Root[anthropic.php]
        API[API Settings]
        Rate[Rate Limiting]
        Cache[Caching]
        Log[Logging]
        Response[Response]
    end

    Root --> API & Rate & Cache & Log & Response

    subgraph API Settings
        API --> Key[API Key]
        API --> URL[Base URL]
        API --> Version[API Version]
        API --> Timeout[Timeout]
    end

    subgraph Rate Limiting
        Rate --> Enabled[Enabled]
        Rate --> Max[Max Requests]
        Rate --> Window[Time Window]
        Rate --> Store[Storage]
    end

    subgraph Caching
        Cache --> CEnabled[Enabled]
        Cache --> TTL[TTL]
        Cache --> Driver[Cache Driver]
        Cache --> Tags[Cache Tags]
    end

    subgraph Logging
        Log --> LEnabled[Enabled]
        Log --> Channel[Log Channel]
        Log --> Level[Log Level]
        Log --> Format[Log Format]
    end

    subgraph Response
        Response --> Transform[Transform]
        Response --> Envelope[Envelope]
        Response --> Metadata[Metadata]
        Response --> Messages[Messages]
    end
```

These architectural diagrams provide:
- Class relationships and structure
- Component interactions
- Package organization
- Data flow patterns
- Event handling system
- Configuration hierarchy

The diagrams help understand:
- System architecture
- Code organization
- Dependencies
- Data flow
- Configuration structure
- Extension points
