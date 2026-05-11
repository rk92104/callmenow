using Microsoft.EntityFrameworkCore;
using QRCodeApp.API.Models;

namespace QRCodeApp.API.Data
{
    public class AppDbContext : DbContext
    {
        public AppDbContext(DbContextOptions<AppDbContext> options) : base(options) { }

        public DbSet<Person> Persons { get; set; }
        public DbSet<QrSticker> QrStickers { get; set; }
        public DbSet<QrScanEvent> QrScanEvents { get; set; }
        public DbSet<MarketingLead> MarketingLeads { get; set; }
        public DbSet<ActiveCallMapping> ActiveCallMappings { get; set; }
        public DbSet<StickerOrder> StickerOrders { get; set; }

        protected override void OnModelCreating(ModelBuilder modelBuilder)
        {
            base.OnModelCreating(modelBuilder);

            modelBuilder.Entity<Person>(entity =>
            {
                entity.ToTable("Persons");
            });

            modelBuilder.Entity<QrSticker>(entity =>
            {
                entity.ToTable("QrStickers");
                entity.HasIndex(e => e.PublicId).IsUnique();
                entity.HasOne(e => e.Person)
                    .WithMany()
                    .HasForeignKey(e => e.PersonId)
                    .OnDelete(DeleteBehavior.SetNull);
            });

            modelBuilder.Entity<QrScanEvent>(entity =>
            {
                entity.ToTable("QrScanEvents");
                entity.HasIndex(e => new { e.QrStickerId, e.VisitorHash });
                entity.HasIndex(e => e.ScannedAtUtc);
                entity.HasOne(e => e.QrSticker)
                    .WithMany()
                    .HasForeignKey(e => e.QrStickerId)
                    .OnDelete(DeleteBehavior.Cascade);
            });

            modelBuilder.Entity<MarketingLead>(entity =>
            {
                entity.ToTable("MarketingLeads");
                entity.HasIndex(e => e.CreatedAtUtc);
                entity.HasIndex(e => e.PhoneNormalized);
            });

            modelBuilder.Entity<StickerOrder>(entity =>
            {
                entity.ToTable("StickerOrders");
                entity.HasIndex(e => e.CreatedAtUtc);
            });
        }
    }
}
