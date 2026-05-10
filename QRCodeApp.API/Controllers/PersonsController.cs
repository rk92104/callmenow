using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;
using QRCodeApp.API.Data;
using QRCodeApp.API.DTOs;
using QRCodeApp.API.Models;
using QRCodeApp.API.Options;
using QRCodeApp.API.Services;
using QRCodeApp.API.Validation;

namespace QRCodeApp.API.Controllers
{
    [Route("api/[controller]")]
    [ApiController]
    [AllowAnonymous]
    public class PersonsController : ControllerBase
    {
        private readonly AppDbContext _context;
        private readonly CallMeNowOptions _callMeNow;

        public PersonsController(AppDbContext context, IOptions<CallMeNowOptions> callMeNow)
        {
            _context = context;
            _callMeNow = callMeNow.Value;
        }

        // GET: api/persons
        [HttpGet]
        public async Task<ActionResult<IEnumerable<Person>>> GetAll()
        {
            var persons = await _context.Persons
                .OrderByDescending(p => p.CreatedAt)
                .ToListAsync();
            return Ok(persons);
        }

        // GET: api/persons/5
        [HttpGet("{id}")]
        public async Task<ActionResult<Person>> GetById(int id)
        {
            var person = await _context.Persons.FindAsync(id);
            if (person == null)
                return NotFound(new { message = "Person not found" });

            return Ok(person);
        }

        // POST: api/persons
        [HttpPost]
        public async Task<ActionResult<Person>> Create([FromBody] PersonDto dto)
        {
            if (!ModelState.IsValid)
                return BadRequest(ModelState);

            var person = new Person
            {
                Name = dto.Name,
                PhoneNumber = dto.PhoneNumber,
                PhoneNumberType = PhoneLineType.Normalize(dto.PhoneNumberType),
                Email = dto.Email,
                Address = dto.Address,
                FatherName = dto.FatherName,
                VehicleRegistration = ContactFieldValidation.NormalizeVehicleRegistration(dto.VehicleRegistration),
                EmergencyContactPhone = (dto.EmergencyContactPhone ?? string.Empty).Trim(),
                EmergencyContactPhoneType = PhoneLineType.Normalize(dto.EmergencyContactPhoneType),
                CreatedAt = DateTime.UtcNow
            };

            _context.Persons.Add(person);
            await _context.SaveChangesAsync();

            return CreatedAtAction(nameof(GetById), new { id = person.Id }, person);
        }

        // PUT: api/persons/5
        [HttpPut("{id}")]
        public async Task<IActionResult> Update(int id, [FromBody] PersonDto dto)
        {
            if (!ModelState.IsValid)
                return BadRequest(ModelState);

            var person = await _context.Persons.FindAsync(id);
            if (person == null)
                return NotFound(new { message = "Person not found" });

            person.Name = dto.Name;
            person.PhoneNumber = dto.PhoneNumber;
            person.PhoneNumberType = PhoneLineType.Normalize(dto.PhoneNumberType);
            person.Email = dto.Email;
            person.Address = dto.Address;
            person.FatherName = dto.FatherName;
            person.VehicleRegistration = ContactFieldValidation.NormalizeVehicleRegistration(dto.VehicleRegistration);
            person.EmergencyContactPhone = (dto.EmergencyContactPhone ?? string.Empty).Trim();
            person.EmergencyContactPhoneType = PhoneLineType.Normalize(dto.EmergencyContactPhoneType);
            person.UpdatedAt = DateTime.UtcNow;

            await _context.SaveChangesAsync();

            return Ok(person);
        }

        // DELETE: api/persons/5
        [HttpDelete("{id}")]
        public async Task<IActionResult> Delete(int id)
        {
            var person = await _context.Persons.FindAsync(id);
            if (person == null)
                return NotFound(new { message = "Person not found" });

            _context.Persons.Remove(person);
            await _context.SaveChangesAsync();

            return Ok(new { message = "Person deleted successfully" });
        }

        // GET: api/persons/5/qrcode — CallMeNow URL only (no PII in QR payload)
        [HttpGet("{id}/qrcode")]
        public async Task<IActionResult> GetQRCode(int id, [FromQuery] int? modulePixels, CancellationToken ct)
        {
            if (await _context.Persons.FindAsync([id], ct) == null)
                return NotFound(new { message = "Person not found" });

            var sticker = await GetOrCreateActiveStickerForPersonAsync(id, ct);
            if (sticker == null)
                return NotFound(new { message = "Could not create QR for this owner." });

            var url = $"{_callMeNow.PublicBaseUrl.TrimEnd('/')}/q/{Uri.EscapeDataString(sticker.PublicId)}";
            var qrCodeBytes = QrPngRenderer.Render(url, modulePixels ?? 20);

            return File(qrCodeBytes, "image/png");
        }

        // GET: api/persons/5/qrcode-base64
        [HttpGet("{id}/qrcode-base64")]
        public async Task<IActionResult> GetQRCodeBase64(int id, [FromQuery] int? modulePixels, CancellationToken ct)
        {
            if (await _context.Persons.FindAsync([id], ct) == null)
                return NotFound(new { message = "Person not found" });

            var sticker = await GetOrCreateActiveStickerForPersonAsync(id, ct);
            if (sticker == null)
                return NotFound(new { message = "Could not create QR for this owner." });

            var url = $"{_callMeNow.PublicBaseUrl.TrimEnd('/')}/q/{Uri.EscapeDataString(sticker.PublicId)}";
            var qrCodeBytes = QrPngRenderer.Render(url, modulePixels ?? 20);
            var base64 = Convert.ToBase64String(qrCodeBytes);

            return Ok(new { qrCodeImage = $"data:image/png;base64,{base64}", qrText = url, publicId = sticker.PublicId });
        }

        /// <summary>
        /// Dashboard owners often have no pre-printed sticker; ensure one active tag exists so QR always works.
        /// </summary>
        private async Task<QrSticker?> GetOrCreateActiveStickerForPersonAsync(int personId, CancellationToken ct)
        {
            var existing = await _context.QrStickers
                .Where(q => q.PersonId == personId && q.Status == QrStickerStatus.Active)
                .OrderByDescending(q => q.ActivatedAt)
                .FirstOrDefaultAsync(ct);

            if (existing != null)
                return existing;

            if (await _context.Persons.FindAsync([personId], ct) == null)
                return null;

            string publicId;
            do
            {
                publicId = QrPublicIdGenerator.CreateNext();
            } while (await _context.QrStickers.AnyAsync(q => q.PublicId == publicId, ct));

            var sticker = new QrSticker
            {
                PublicId = publicId,
                IvrAccessCode = await IvrAccessCodeHelper.AllocateNewAsync(_context, ct),
                ProductType = "CarSticker",
                Status = QrStickerStatus.Active,
                PersonId = personId,
                ScanCount = 0,
                UniqueScannerCount = 0,
                CreatedAt = DateTime.UtcNow,
                ActivatedAt = DateTime.UtcNow
            };

            _context.QrStickers.Add(sticker);
            await _context.SaveChangesAsync(ct);
            return sticker;
        }
    }
}
