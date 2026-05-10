using System.Text.Json.Serialization;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;
using QRCodeApp.API.Data;
using QRCodeApp.API.Models;
using QRCodeApp.API.Options;
using QRCodeApp.API.Services;
using Microsoft.AspNetCore.StaticFiles;
using Microsoft.Extensions.FileProviders;

var builder = WebApplication.CreateBuilder(args);

builder.Services.Configure<CallMeNowOptions>(
    builder.Configuration.GetSection(CallMeNowOptions.SectionName));

builder.Services.AddHttpContextAccessor();

// Add services to the container — always emit booleans/nulls so Angular sees razorpayEnabled reliably
builder.Services.AddControllers()
    .AddJsonOptions(o =>
    {
        o.JsonSerializerOptions.DefaultIgnoreCondition = JsonIgnoreCondition.Never;
    });
builder.Services.AddHttpClient<RazorpayPaymentService>(c =>
{
    c.BaseAddress = new Uri("https://api.razorpay.com/");
    c.Timeout = TimeSpan.FromSeconds(30);
});
builder.Services.AddHttpClient<ExotelConnectService>(c =>
{
    c.Timeout = TimeSpan.FromSeconds(30);
});
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

// Configure SQL Server
builder.Services.AddDbContext<AppDbContext>(options =>
{
    options.UseSqlServer(builder.Configuration.GetConnectionString("DefaultConnection"));
    // Local dev: model snapshot can drift from last migration; Migrate() otherwise throws (EF10+).
    if (builder.Environment.IsDevelopment())
    {
        options.ConfigureWarnings(w => w.Ignore(RelationalEventId.PendingModelChangesWarning));
    }
});

// CORS: ng serve uses localhost:4200 OR 127.0.0.1:4200 (different origins). Include API self-origin for edge cases.
builder.Services.AddCors(options =>
{
    options.AddPolicy("AllowAngular", policy =>
    {
        if (builder.Environment.IsDevelopment())
        {
            policy.SetIsOriginAllowed(_ => true)
                  .AllowAnyHeader()
                  .AllowAnyMethod();
        }
        else
        {
            policy.WithOrigins(
                    "http://localhost:4200",
                    "http://127.0.0.1:4200",
                    "http://localhost:5000",
                    "http://127.0.0.1:5000")
                  .AllowAnyHeader()
                  .AllowAnyMethod();
        }
    });
});

var app = builder.Build();

using (var scope = app.Services.CreateScope())
{
    var db = scope.ServiceProvider.GetRequiredService<AppDbContext>();
    try
    {
        db.Database.Migrate();
        QrStickerSchemaRepair.ApplyIfNeeded(db);

        if (app.Environment.IsDevelopment() && !db.QrStickers.Any())
        {
            for (var i = 0; i < 3; i++)
            {
                string publicId;
                do
                {
                    publicId = QrPublicIdGenerator.CreateNext();
                } while (db.QrStickers.Any(q => q.PublicId == publicId));

                string ivr;
                do
                {
                    ivr = Random.Shared.Next(0, 1_000_000).ToString("D6");
                } while (db.QrStickers.Any(q => q.IvrAccessCode == ivr));

                db.QrStickers.Add(new QrSticker
                {
                    PublicId = publicId,
                    IvrAccessCode = ivr,
                    ProductType = i == 0 ? "CarSticker" : i == 1 ? "KeyFinder" : "LuggageTag",
                    Status = QrStickerStatus.Unused,
                    CreatedAt = DateTime.UtcNow
                });
            }

            db.SaveChanges();
        }
    }
    catch (Exception ex)
    {
        app.Logger.LogWarning(ex, "Database migration/repair skipped (startup continues).");
    }
}

// Configure the HTTP request pipeline
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

app.UseCors("AllowAngular");

// Single web root = wwwroot/browser (Angular output + /assets, /marketing from public)
var browserRoot = Path.Combine(app.Environment.ContentRootPath, "wwwroot", "browser");
var browserFiles = new PhysicalFileProvider(browserRoot);

app.UseDefaultFiles(new DefaultFilesOptions
{
    FileProvider = browserFiles,
    RequestPath = ""
});
var staticForBrowser = new StaticFileOptions
{
    FileProvider = browserFiles,
    RequestPath = ""
};
staticForBrowser.OnPrepareResponse = ctx =>
{
    var name = ctx.File.Name;
    var host = ctx.Context.Request.Host.Host ?? string.Empty;
    var isLocalHost = string.Equals(host, "localhost", StringComparison.OrdinalIgnoreCase) ||
                      string.Equals(host, "127.0.0.1", StringComparison.OrdinalIgnoreCase);
    // Always avoid caching the SPA shell: stale index.html can pair with old or new hashed bundles after deploy.
    if (string.Equals(name, "index.html", StringComparison.OrdinalIgnoreCase))
    {
        ctx.Context.Response.Headers.CacheControl = "no-cache, no-store, must-revalidate, max-age=0";
        ctx.Context.Response.Headers.Pragma = "no-cache";
        return;
    }

    if (isLocalHost || app.Environment.IsDevelopment())
    {
        // Dev: :5000 serves wwwroot/browser — disable caching for all static assets (svg/png/fonts too),
        // otherwise the browser can keep stale logos/CSS while JS/CSS already bypass cache.
        ctx.Context.Response.Headers.CacheControl = "no-cache, no-store, must-revalidate, max-age=0";
        ctx.Context.Response.Headers.Pragma = "no-cache";
    }
};

app.UseStaticFiles(staticForBrowser);

app.UseAuthorization();

app.MapControllers();

app.MapFallbackToFile("index.html", staticForBrowser);

app.Run();
